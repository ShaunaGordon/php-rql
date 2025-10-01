<?php

namespace r;

use r\Datum\ArrayDatum;
use r\Datum\BoolDatum;
use r\Datum\NullDatum;
use r\Datum\NumberDatum;
use r\Datum\ObjectDatum;
use r\Datum\StringDatum;
use r\Exceptions\RqlDriverError;
use r\Exceptions\RqlServerError;
use r\Options\FormatMode;
use r\Options\RunOptions;
use r\ProtocolBuffer\QueryQueryType;
use r\ProtocolBuffer\ResponseResponseType;
use r\Queries\Dbs\Db;

class Connection extends DatumConverter implements ConnectionInterface
{
    public string $defaultDbName;
    private $socket;
    private string $host;
    private int $port;
    private ?Db $defaultDb;
    private string $user;
    private string $password;
    private ?array $activeTokens;
    private ?int $timeout;
    private array|bool|null $ssl;

    public function __construct(ConnectionOptions $connectionOptions)
    {
        $this->host = $connectionOptions->host;
        $this->port = $connectionOptions->port;
        $this->user = $connectionOptions->user;
        $this->password = $connectionOptions->password;
        $this->timeout = $connectionOptions->timeout;
        $this->ssl = $connectionOptions->ssl;

        $this->useDb($connectionOptions->db);
        $this->setTimeout($connectionOptions->timeout);

        $this->connect();
    }

    public function useDb(?string $dbName): void
    {
        if (empty($dbName)) {
            $this->defaultDb = null;
            $this->defaultDbName = '';

            return;
        }

        $this->defaultDbName = $dbName;
        $this->defaultDb = new Db($dbName);
    }

    public function setTimeout(int|float|null $timeout): void
    {
        if (empty($timeout)) {
            return;
        }

        $this->applyTimeout($timeout);
        $this->timeout = $timeout;
    }

    private function applyTimeout(int|float $timeout): void
    {
        if ($this->isOpen()) {
            if (!stream_set_timeout($this->socket, $timeout)) {
                throw new RqlDriverError('Could not set timeout');
            }
        }
    }

    public function isOpen(): bool
    {
        return isset($this->socket);
    }

    private function connect(): void
    {
        if ($this->isOpen()) {
            throw new RqlDriverError('Already connected');
        }

        if ($this->ssl) {
            if (is_array($this->ssl)) {
                $context = stream_context_create(['ssl' => $this->ssl]);
            } else {
                $context = null;
            }
            $this->socket = stream_socket_client(
                'ssl://'.$this->host.':'.$this->port,
                $errno,
                $errstr,
                ini_get('default_socket_timeout'),
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            $this->socket = stream_socket_client('tcp://'.$this->host.':'.$this->port, $errno, $errstr);
        }
        if ($errno != 0 || $this->socket === false) {
            $this->socket = null;
            throw new RqlDriverError('Unable to connect: '.$errstr);
        }
        if ($this->timeout) {
            $this->applyTimeout($this->timeout);
        }

        $handshake = new Handshake($this->user, $this->password);
        $handshakeResponse = null;
        while (true) {
            if (!$this->isOpen()) {
                throw new RqlDriverError('Not connected');
            }
            try {
                $msg = $handshake->nextMessage($handshakeResponse);
            } catch (\Exception $e) {
                $this->close(false);
                throw $e;
            }
            if ($msg === null) {
                // Handshake is complete
                break;
            }
            if ($msg != '') {
                $this->sendStr($msg);
            }
            // Read null-terminated response
            $handshakeResponse = '';
            while (true) {
                $ch = stream_get_contents($this->socket, 1);
                if ($ch === false || strlen($ch) < 1) {
                    $this->close(false);
                    throw new RqlDriverError('Unable to read from socket during handshake. Disconnected.');
                }
                if ($ch === chr(0)) {
                    break;
                } else {
                    $handshakeResponse .= $ch;
                }
            }
        }
    }

    public function close(bool $noreplyWait = true): void
    {
        if (!$this->isOpen()) {
            throw new RqlDriverError('Not connected.');
        }

        if ($noreplyWait) {
            $this->noreplyWait();
        }

        fclose($this->socket);
        $this->socket = null;
        $this->activeTokens = null;
    }

    public function noreplyWait(): void
    {
        if (!$this->isOpen()) {
            throw new RqlDriverError('Not connected.');
        }

        // Generate a token for the request
        $token = $this->generateToken();

        // Send the request
        $jsonQuery = [QueryQueryType::PB_NOREPLY_WAIT->value];
        $this->sendQuery($token, $jsonQuery);

        // Await the response
        $response = $this->receiveResponse($token);
        $type = ResponseResponseType::tryFrom($response['t']);

        if ($type !== ResponseResponseType::PB_WAIT_COMPLETE) {
            throw new RqlDriverError('Unexpected response type to noreplyWait query.');
        }
    }

    private function generateToken(): int
    {
        static $token = -1;
        $token = ($token + 1) % (1 << 30);

        return $token;
    }

    private function sendQuery(int $token, mixed $json): void
    {
        // PHP by default loses some precision when encoding floats, so we temporarily
        // bump up the `precision` option to avoid this.
        // The 17 assumes IEEE-754 double precision numbers.
        // Source: http://docs.oracle.com/cd/E19957-01/806-3568/ncg_goldberg.html
        //         "The same argument applied to double precision shows that 17 decimal
        //          digits are required to recover a double precision number."
        $previousPrecision = ini_set('precision', 17);
        $request = json_encode($json);
        if ($previousPrecision !== false) {
            ini_set('precision', $previousPrecision);
        }
        if ($request === false) {
            throw new RqlDriverError('Failed to encode query as JSON: '.json_last_error());
        }

        $requestSize = pack('V', strlen($request));
        $binaryToken = pack('V', $token).pack('V', 0);
        $this->sendStr($binaryToken.$requestSize.$request);
    }

    private function sendStr(string $s): void
    {
        $bytesWritten = 0;
        while ($bytesWritten < strlen($s)) {
            $result = fwrite($this->socket, substr($s, $bytesWritten));
            if ($result === false || $result === 0) {
                $metaData = stream_get_meta_data($this->socket);
                $this->close(false);
                if ($metaData['timed_out']) {
                    throw new RqlDriverError('Timed out while writing to socket. Disconnected. Call setTimeout(seconds) on the connection to change the timeout.');
                }
                throw new RqlDriverError('Unable to write to socket. Disconnected.');
            }
            $bytesWritten += $result;
        }
    }

    private function receiveResponse(int $token, ?Query $query = null, bool $noChecks = false): array
    {
        $responseHeader = $this->receiveStr(4 + 8);
        $responseHeader = unpack('Vtoken/Vtoken2/Vsize', $responseHeader);
        $responseToken = $responseHeader['token'];
        if ($responseHeader['token2'] != 0) {
            throw new RqlDriverError('Invalid response from server: Invalid token.');
        }
        $responseSize = $responseHeader['size'];
        $responseBuf = $this->receiveStr($responseSize);

        try {
            $response = json_decode($responseBuf, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RqlDriverError('Invalid response from server: Failed to decode JSON.', previous: $exception);
        }

        if (!$noChecks) {
            $this->checkResponse($response, $responseToken, $token, $query);
        }

        return $response;
    }

    private function receiveStr(int $length): string
    {
        $s = '';
        while (strlen($s) < $length) {
            $partialS = stream_get_contents($this->socket, $length - strlen($s));
            if ($partialS === false || feof($this->socket)) {
                $metaData = stream_get_meta_data($this->socket);
                $this->close(false);
                if ($metaData['timed_out']) {
                    throw new RqlDriverError('Timed out while reading from socket. Disconnected. Call setTimeout(seconds) on the connection to change the timeout.');
                }
                throw new RqlDriverError('Unable to read from socket. Disconnected.');
            }
            $s = $s.$partialS;
        }

        return $s;
    }

    private function checkResponse(array $response, int $responseToken, int $token, ?Query $query = null)
    {
        $type = ResponseResponseType::tryFrom(
            $response['t'] ?? throw new RqlDriverError('Response message has no type.')
        );

        if ($type === ResponseResponseType::PB_CLIENT_ERROR) {
            throw new RqlDriverError('Server says PHP-RQL is buggy: '.$response['r'][0]);
        }

        if ($responseToken !== $token) {
            throw new RqlDriverError('Received wrong token. Response does not match the request. Expected '.$token.', received '.$responseToken);
        }

        if ($type === ResponseResponseType::PB_COMPILE_ERROR) {
            $backtrace = null;
            if (isset($response['b'])) {
                $backtrace = Backtrace::decodeServerResponse($response['b']);
            }
            throw new RqlServerError('Compile error: '.$response['r'][0], $query, $backtrace);
        } elseif ($type === ResponseResponseType::PB_RUNTIME_ERROR) {
            $backtrace = null;
            if (isset($response['b'])) {
                $backtrace = Backtrace::decodeServerResponse($response['b']);
            }
            throw new RqlServerError('Runtime error: '.$response['r'][0], $query, $backtrace);
        }
    }

    public function __destruct()
    {
        if ($this->isOpen()) {
            $this->close(false);
        }
    }

    public function reconnect(bool $noreplyWait = true): void
    {
        if ($this->isOpen()) {
            $this->close($noreplyWait);
        }
        $this->connect();
    }

    public function server(): array|string
    {
        if (!$this->isOpen()) {
            throw new RqlDriverError('Not connected.');
        }

        // Generate a token for the request
        $token = $this->generateToken();

        // Send the request
        $jsonQuery = [QueryQueryType::PB_SERVER_INFO->value];
        $this->sendQuery($token, $jsonQuery);

        // Await the response
        $response = $this->receiveResponse($token);
        $type = ResponseResponseType::tryFrom($response['t']);

        if ($type !== ResponseResponseType::PB_SERVER_INFO) {
            throw new RqlDriverError('Unexpected response type to server info query.');
        }

        $toNativeOptions = [];

        return $this->createDatumFromResponse($response)->toNative($toNativeOptions);
    }

    private function createDatumFromResponse($response
    ): ObjectDatum|StringDatum|BoolDatum|NumberDatum|NullDatum|ArrayDatum {
        return self::decodedJSONToDatum($response['r'][0]);
    }

    public function run(
        Query $query,
        RunOptions $options = new RunOptions(),
        ?string &$profile = ''
    ): Cursor|array|string|\DateTimeInterface|float|int|bool|null {
        if (!$this->isOpen()) {
            throw new RqlDriverError('Not connected.');
        }

        // Grab PHP-RQL specific options
        $toNativeOptions = [
            'binaryFormat' => $options->binary_format ?? FormatMode::Native,
            'timeFormat' => $options->time_format ?? FormatMode::Native,
        ];

        // Generate a token for the request
        $token = $this->generateToken();

        // Send the request
        $globalOptargs = $this->convertOptions($options);
        $globalOptargs['db'] = $globalOptargs['db'] ?? $this->defaultDb?->encodeServerRequest();

        $jsonQuery = [
            QueryQueryType::PB_START->value,
            $query->encodeServerRequest(),
            (object) array_filter($globalOptargs),
        ];

        $this->sendQuery($token, $jsonQuery);

        if ($options->noreply) {
            return null;
        }

        // Await the response
        $response = $this->receiveResponse($token, $query);
        $type = ResponseResponseType::tryFrom($response['t']);

        if ($type === ResponseResponseType::PB_SUCCESS_PARTIAL) {
            $this->activeTokens[$token] = true;
        }

        if (isset($response['p'])) {
            $profile = self::decodedJSONToDatum($response['p'])->toNative($toNativeOptions);
        }

        if ($type === ResponseResponseType::PB_SUCCESS_ATOM) {
            return $this->createDatumFromResponse($response)->toNative($toNativeOptions);
        }

        return $this->createCursorFromResponse($response, $token, $response['n'], $toNativeOptions);
    }

    private function convertOptions(array|object|null $options): array
    {
        $opts = [];

        foreach ((array) $options as $key => $value) {
            if (in_array($key, ['binary_format', 'time_format']) || null === $value) {
                continue;
            }
            $opts[$key] = $this->nativeToDatum($value)->encodeServerRequest();
        }

        return $opts;
    }

    private function createCursorFromResponse($response, $token, $notes, $toNativeOptions): Cursor
    {
        return new Cursor($this, $response, $token, $notes, $toNativeOptions);
    }

    public function continueQuery(int $token): array
    {
        if (!$this->isOpen()) {
            throw new RqlDriverError('Not connected.');
        }

        // Send the request
        $jsonQuery = [QueryQueryType::PB_CONTINUE->value];
        $this->sendQuery($token, $jsonQuery);

        // Await the response
        $response = $this->receiveResponse($token);
        $type = ResponseResponseType::tryFrom($response['t']);

        if ($type != ResponseResponseType::PB_SUCCESS_PARTIAL) {
            unset($this->activeTokens[$token]);
        }

        return $response;
    }

    public function stopQuery(int $token): array
    {
        if (!$this->isOpen()) {
            throw new RqlDriverError('Not connected.');
        }

        // Send the request
        $jsonQuery = [QueryQueryType::PB_STOP->value];
        $this->sendQuery($token, $jsonQuery);

        // Await the response (but don't check for errors. the stop response doesn't even have a type)
        $response = $this->receiveResponse($token, null, true);

        unset($this->activeTokens[$token]);

        return $response;
    }
}
