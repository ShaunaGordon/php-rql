<?php

namespace r;

use r\Datum\ArrayDatum;
use r\Datum\BoolDatum;
use r\Datum\Datum;
use r\Datum\NullDatum;
use r\Datum\NumberDatum;
use r\Datum\ObjectDatum;
use r\Datum\StringDatum;
use r\Exceptions\RqlDriverError;
use r\Options\CircleOptions;
use r\Options\DistanceOptions;
use r\Options\HttpOptions;
use r\Options\Iso8601Options;
use r\Options\RandomOptions;
use r\Options\TableCreateOptions;
use r\Options\TableOptions;
use r\Options\UnionOptions;
use r\Ordering\Asc;
use r\Ordering\Desc;
use r\Queries\Control\Args;
use r\Queries\Control\Branch;
use r\Queries\Control\Error;
use r\Queries\Control\Http;
use r\Queries\Control\Js;
use r\Queries\Control\Range;
use r\Queries\Control\RDo;
use r\Queries\Dates\April;
use r\Queries\Dates\August;
use r\Queries\Dates\December;
use r\Queries\Dates\EpochTime;
use r\Queries\Dates\February;
use r\Queries\Dates\Friday;
use r\Queries\Dates\Iso8601;
use r\Queries\Dates\January;
use r\Queries\Dates\July;
use r\Queries\Dates\June;
use r\Queries\Dates\March;
use r\Queries\Dates\May;
use r\Queries\Dates\Monday;
use r\Queries\Dates\November;
use r\Queries\Dates\Now;
use r\Queries\Dates\October;
use r\Queries\Dates\Saturday;
use r\Queries\Dates\September;
use r\Queries\Dates\Sunday;
use r\Queries\Dates\Thursday;
use r\Queries\Dates\Time;
use r\Queries\Dates\Tuesday;
use r\Queries\Dates\Wednesday;
use r\Queries\Dbs\Db;
use r\Queries\Dbs\DbCreate;
use r\Queries\Dbs\DbDrop;
use r\Queries\Dbs\DbList;
use r\Queries\Geo\Circle;
use r\Queries\Geo\Distance;
use r\Queries\Geo\GeoJSON;
use r\Queries\Geo\Intersects;
use r\Queries\Geo\Line;
use r\Queries\Geo\Point;
use r\Queries\Geo\Polygon;
use r\Queries\Manipulation\GetField;
use r\Queries\Math\Add;
use r\Queries\Math\BitAnd;
use r\Queries\Math\BitNot;
use r\Queries\Math\BitOr;
use r\Queries\Math\BitSal;
use r\Queries\Math\BitSar;
use r\Queries\Math\BitXor;
use r\Queries\Math\Ceil;
use r\Queries\Math\Div;
use r\Queries\Math\Eq;
use r\Queries\Math\Floor;
use r\Queries\Math\Ge;
use r\Queries\Math\Gt;
use r\Queries\Math\Le;
use r\Queries\Math\Lt;
use r\Queries\Math\Mod;
use r\Queries\Math\Mul;
use r\Queries\Math\Ne;
use r\Queries\Math\Not;
use r\Queries\Math\RAnd;
use r\Queries\Math\Random;
use r\Queries\Math\ROr;
use r\Queries\Math\Round;
use r\Queries\Math\Sub;
use r\Queries\Misc\Grant;
use r\Queries\Misc\Maxval;
use r\Queries\Misc\Minval;
use r\Queries\Misc\Uuid;
use r\Queries\Tables\Table;
use r\Queries\Tables\TableCreate;
use r\Queries\Tables\TableDrop;
use r\Queries\Tables\TableList;
use r\Queries\Transformations\MapMultiple;
use r\Queries\Transformations\Union;
use r\ValuedQuery\ImplicitVar;
use r\ValuedQuery\Json;
use r\ValuedQuery\Literal;
use r\ValuedQuery\MakeArray;
use r\ValuedQuery\MakeObject;
use r\ValuedQuery\RObject;

// ------------- Global functions in namespace r -------------

/**
 * Connect to a database.
 *
 * @see https://rethinkdb.com/api/javascript/connect/
 */
function connect(
    ConnectionOptions $connectionOptions
): Connection {
    return new Connection($connectionOptions);
}

function connectAsync(ConnectionOptions $connectionOptions): AmpConnection
{
    return new AmpConnection($connectionOptions);
}

/**
 * Reference a database.
 *
 * The db command is optional. If it is not present in a query, the query will run against the default database for the
 * connection, specified in the db argument to connect.
 *
 * @see https://rethinkdb.com/api/javascript/db/
 *
 * @param string $dbName the name of the database to use
 */
function db(string $dbName): Db
{
    return new Db($dbName);
}

/**
 * Create a database. A RethinkDB database is a collection of tables, similar to relational databases.
 *
 * If successful, the command returns an object with two fields:
 *
 * = dbs_created: always 1.
 * = config_changes: a list containing one object with two fields, old_val and new_val:
 *  = old_val: always null.
 *  = new_val: the database’s new config value.
 * If a database with the same name already exists, the command throws ReqlRuntimeError.
 *
 * Note: Only alphanumeric characters, hyphens and underscores are valid for the database name.
 *
 * @see https://rethinkdb.com/api/javascript/db_create
 *
 * @param string $dbName the name of the database to create
 */
function dbCreate(string $dbName): DbCreate
{
    return new DbCreate($dbName);
}

function grant(string $user, ...$permissions): Grant
{
    return new Grant(null, $user, ...$permissions);
}

/**
 * Drop a database. The database, all its tables, and corresponding data will be deleted.
 *
 * If successful, the command returns an object with two fields:
 *
 * - dbs_dropped: always 1.
 * - tables_dropped: the number of tables in the dropped database.
 * - config_changes: a list containing one two-field object, old_val and new_val:
 *  - old_val: the database’s original config value.
 *  - new_val: always null.
 * If the given database does not exist, the command throws ReqlRuntimeError.
 *
 * @see https://rethinkdb.com/api/javascript/db_drop
 *
 * @param string $dbName The database to drop
 */
function dbDrop(string $dbName): DbDrop
{
    return new DbDrop($dbName);
}

/**
 * List all database names in the system. The result is a list of strings.
 *
 * @see https://rethinkdb.com/api/javascript/db_list
 */
function dbList(): DbList
{
    return new DbList();
}

/**
 * Return all documents in a table. Other commands may be chained after table to return a subset of documents (such as
 * get and filter) or perform further processing.
 *
 * @see https://rethinkdb.com/api/javascript/table/
 *
 * @param string $tableName the name of the table to read from
 *
 * @throws RqlDriverError
 */
function table(string $tableName, TableOptions $options = new TableOptions()): Table
{
    return new Table(null, $tableName, $options);
}

/**
 * Create a table. A RethinkDB table is a collection of JSON documents.
 *
 * If successful, the command returns an object with two fields:
 *
 * - tables_created: always 1.
 * - config_changes: a list containing one two-field object, old_val and new_val:
 *   - old_val: always null.
 *   - new_val: the table’s new config value.
 * If a table with the same name already exists, the command throws ReqlOpFailedError.
 *
 * @see https://rethinkdb.com/api/javascript/table_create/
 *
 * @param string $tableName The table name to create
 */
function tableCreate(string $tableName, TableCreateOptions $options = new TableCreateOptions()): TableCreate
{
    return new TableCreate(null, $tableName, $options);
}

/**
 * Drop a table from a database. The table and all its data will be deleted.
 *
 * If successful, the command returns an object with two fields:
 *
 * - tables_dropped: always 1.
 * - config_changes: a list containing one two-field object, old_val and new_val:
 *   - old_val: the dropped table’s config value.
 *   - new_val: always null.
 * If the given table does not exist in the database, the command throws ReqlRuntimeError.
 *
 * @see https://rethinkdb.com/api/javascript/table_drop
 *
 * @param string $tableName The table to drop
 */
function tableDrop(string $tableName): TableDrop
{
    return new TableDrop(null, $tableName);
}

/**
 * List all table names in a database. The result is a list of strings.
 *
 * @see https://rethinkdb.com/api/javascript/table_list
 */
function tableList(): TableList
{
    return new TableList(null);
}

/**
 * Call an anonymous function using return values from other ReQL commands or queries as arguments.
 *
 * The last argument to do (or, in some forms, the only argument) is an expression or an anonymous function which
 * receives values from either the previous arguments or from prefixed commands chained before do. The do command is
 * essentially a single-element map, letting you map a function over just one document. This allows you to bind a query
 * result to a local variable within the scope of do, letting you compute the result just once and reuse it in a complex
 * expression or in a series of ReQL commands.
 *
 * Arguments passed to the do function must be basic data types, and cannot be streams or selections. (Read about ReQL
 * data types.) While the arguments will all be evaluated before the function is executed, they may be evaluated in any
 * order, so their values should not be dependent on one another. The type of do’s result is the type of the value
 * returned from the function or last expression.
 *
 * @see https://rethinkdb.com/api/javascript/do/
 */
function rDo(array $args, Query|callable $inExpr): RDo
{
    return new RDo($args, $inExpr);
}

/**
 * r.args is a special term that’s used to splice an array of arguments into another term. This is useful when you want
 * to call a variadic term such as getAll with a set of arguments produced at runtime.
 *
 * This is analogous to using apply in JavaScript. (However, note that args evaluates all its arguments before passing
 * them into the parent term, even if the parent term otherwise allows lazy evaluation.)
 *
 * @see https://rethinkdb.com/api/javascript/args
 */
function args(array $args): Args
{
    return new Args($args);
}

/**
 * Perform a branching conditional equivalent to if-then-else.
 *
 * The branch command takes 2n+1 arguments: pairs of conditional expressions and commands to be executed if the
 * conditionals return any value but false or null (i.e., “truthy” values), with a final “else” command to be evaluated
 * if all of the conditionals are false or null.
 *
 * @see https://rethinkdb.com/api/javascript/branch
 */
function branch(Query $test, ...$branches): Branch
{
    return new Branch($test, ...$branches);
}

/**
 * Returns the currently visited document.
 *
 * @see https://rethinkdb.com/api/javascript/row/
 *
 * @param string|Query|null $attribute Shortcut for row()(attribute)
 */
function row(string|Query|null $attribute = null): GetField|ImplicitVar
{
    if (null !== $attribute) {
        // A shortcut to do row()($attribute)
        return new GetField(new ImplicitVar(), $attribute);
    } else {
        return new ImplicitVar();
    }
}

/**
 * Create a javascript expression.
 *
 * timeout is the number of seconds before r.js times out. The default value is 5 seconds.
 *
 * Whenever possible, you should use native ReQL commands rather than r.js for better performance.
 *
 * @see https://rethinkdb.com/api/javascript/js/
 *
 * @param string         $code    The js code to execute
 * @param int|float|null $timeout The timeout in seconds
 */
function js(string $code, int|float|null $timeout = null): Js
{
    return new Js($code, $timeout);
}

/**
 * Throw a runtime error. If called with no arguments inside the second argument to default, re-throw the current error.
 *
 * @see https://rethinkdb.com/api/javascript/error/
 */
function error(?string $message = null): Error
{
    return new Error($message);
}

/**
 * Construct a ReQL JSON object from a native object.
 *
 * @throws RqlDriverError
 */
function expr(
    mixed $obj
): MakeObject|ObjectDatum|Iso8601|MakeArray|StringDatum|BoolDatum|NumberDatum|Query|NullDatum|ArrayDatum {
    if ($obj instanceof Query) {
        return $obj;
    }

    $dc = new DatumConverter();

    return $dc->nativeToDatum($obj);
}

/**
 * Encapsulate binary data within a query.
 *
 * @see https://rethinkdb.com/api/javascript/binary/
 *
 * @throws RqlDriverError
 */
function binary(string $str): Datum
{
    $encodedStr = base64_encode($str);
    if ($encodedStr === false) {
        throw new RqlDriverError("Failed to Base64 encode '".$str."'");
    }
    $pseudo = ['$reql_type$' => 'BINARY', 'data' => $encodedStr];

    $dc = new DatumConverter();

    return $dc->nativeToDatum($pseudo);
}

/**
 * @see https://rethinkdb.com/api/javascript/order_by/
 */
function desc(callable|string $attribute): Desc
{
    return new Desc($attribute);
}

/**
 * @see https://rethinkdb.com/api/javascript/order_by/
 */
function asc(callable|string $attribute): Asc
{
    return new Asc($attribute);
}

/**
 * Parse a JSON string on the server.
 *
 * @see https://rethinkdb.com/api/javascript/json/
 */
function json(string|Query $json): Json
{
    return new Json($json);
}

/**
 * Retrieve data from the specified URL over HTTP. The return type depends on the resultFormat option, which checks the
 * Content-Type of the response by default. Make sure that you never use this command for user provided URLs.
 *
 * @see https://rethinkdb.com/api/javascript/http/
 */
function http(string $url, HttpOptions $opts = new HttpOptions()): Http
{
    return new Http($url, $opts);
}

/**
 * Creates an object from a list of key-value pairs, where the keys must be strings. r.object(A, B, C, D) is equivalent
 * to r.expr([[A, B], [C, D]]).coerceTo('OBJECT').
 *
 * @see https://rethinkdb.com/api/javascript/object/
 */
function rObject(mixed ...$object): RObject
{
    return new RObject(...$object);
}

/**
 * Replace an object in a field instead of merging it with an existing object in a merge or update operation. Using
 * literal with no arguments in a merge or update operation will remove the corresponding field.
 *
 * @see https://rethinkdb.com/api/javascript/literal/
 */
function literal(...$args): Literal
{
    if (count($args) == 0) {
        return new Literal();
    } else {
        return new Literal($args[0]);
    }
}

/**
 * Sum two or more numbers, or concatenate two or more strings or arrays.
 *
 * The add command can be called in either prefix or infix form; both forms are equivalent. Note that ReQL will not
 * perform type coercion. You cannot, for example, add a string and a number together.
 *
 * @see https://rethinkdb.com/api/javascript/add/
 */
function add(string|int|float|array|Query $expr1, string|int|float|array|Query $expr2): Add
{
    return new Add($expr1, $expr2);
}

/**
 * Subtract two numbers.
 *
 * @see https://rethinkdb.com/api/javascript/sub/
 */
function sub(int|float|Query $expr1, int|float|Query $expr2): Sub
{
    return new Sub($expr1, $expr2);
}

/**
 * Multiply two numbers, or make a periodic array.
 *
 * @see https://rethinkdb.com/api/javascript/mul/
 */
function mul(int|float|Query $expr1, int|float|Query $expr2): Mul
{
    return new Mul($expr1, $expr2);
}

/**
 * Divide two numbers.
 *
 * @see https://rethinkdb.com/api/javascript/div/
 */
function div(int|float|Query $expr1, int|float|Query $expr2): Div
{
    return new Div($expr1, $expr2);
}

/**
 * Find the remainder when dividing two numbers.
 *
 * @see https://rethinkdb.com/api/javascript/mod/
 */
function mod(int|float|Query $expr1, int|float|Query $expr2): Mod
{
    return new Mod($expr1, $expr2);
}

/**
 * Compute the logical “and” of one or more values.
 *
 * The and command can be used as an infix operator after its first argument (r.expr(true).and(false)) or given all of
 * its arguments as parameters (r.and(true,false)).
 *
 * @see https://rethinkdb.com/api/javascript/and/
 */
function rAnd(bool|Query $expr1, bool|Query $expr2): RAnd
{
    return new RAnd($expr1, $expr2);
}

/**
 * Compute the logical “or” of one or more values.
 *
 * The or command can be used as an infix operator after its first argument (r.expr(true).or(false)) or given all of
 * its arguments as parameters (r.or(true,false)).
 *
 * @see https://rethinkdb.com/api/javascript/or/
 */
function rOr(bool|Query $expr1, bool|Query $expr2): ROr
{
    return new ROr($expr1, $expr2);
}

/**
 * Test if two or more values are equal.
 *
 * @see https://rethinkdb.com/api/javascript/eq/
 */
function eq(mixed $expr1, mixed $expr2): Eq
{
    return new Eq($expr1, $expr2);
}

/**
 * Test if two or more values are not equal.
 *
 * @see https://rethinkdb.com/api/javascript/ne/
 */
function ne(mixed $expr1, mixed $expr2): Ne
{
    return new Ne($expr1, $expr2);
}

/**
 * Compare values, testing if the left-hand value is greater than the right-hand.
 *
 * @see https://rethinkdb.com/api/javascript/gt/
 */
function gt(mixed $expr1, mixed $expr2): Gt
{
    return new Gt($expr1, $expr2);
}

/**
 * Compare values, testing if the left-hand value is greater than or equal to the right-hand.
 *
 * @see https://rethinkdb.com/api/javascript/ge/
 */
function ge(mixed $expr1, mixed $expr2): Ge
{
    return new Ge($expr1, $expr2);
}

/**
 * Compare values, testing if the left-hand value is less than the right-hand.
 *
 * @see https://rethinkdb.com/api/javascript/lt/
 */
function lt(mixed $expr1, mixed $expr2): Lt
{
    return new Lt($expr1, $expr2);
}

/**
 * Compare values, testing if the left-hand value is less than or equal to the right-hand.
 *
 * @see https://rethinkdb.com/api/javascript/le/
 */
function le(mixed $expr1, mixed $expr2): Le
{
    return new Le($expr1, $expr2);
}

/**
 * Compute the logical inverse (not) of an expression.
 *
 * not can be called either via method chaining, immediately after an expression that evaluates as a boolean value, or
 * by passing the expression as a parameter to not. All values that are not false or null will be converted to true.
 */
function not(bool|Query $expr): Not
{
    return new Not($expr);
}

/**
 * A bitwise AND is a binary operation that takes two equal-length binary representations and performs the logical
 * AND operation on each pair of the corresponding bits, which is equivalent to multiplying them. Thus, if both bits
 * in the compared position are 1, the bit in the resulting binary representation is 1 (1 × 1 = 1); otherwise, the
 * result is 0 (1 × 0 = 0 and 0 × 0 = 0).
 */
function bitAnd(int|float|Query $left, int|float|Query ...$other): BitAnd
{
    return new BitAnd($left, $other);
}

/**
 * A bitwise OR is a binary operation that takes two bit patterns of equal length and performs the logical inclusive
 * OR operation on each pair of corresponding bits. The result in each position is 0 if both bits are 0, while
 * otherwise the result is 1.
 */
function bitOr(int|float|Query $left, int|float|Query ...$other): BitOr
{
    return new BitOr($left, $other);
}

/**
 * A bitwise XOR is a binary operation that takes two bit patterns of equal length and performs the logical
 * exclusive OR operation on each pair of corresponding bits. The result in each position is 1 if only the first bit
 * is 1 or only the second bit is 1, but will be 0 if both are 0 or both are 1. In this we perform the comparison of
 * two bits, being 1 if the two bits are different, and 0 if they are the same.
 */
function bitXor(int|float|Query $left, int|float|Query ...$other): BitXor
{
    return new BitXor($left, $other);
}

/**
 * A bitwise NOT, or complement, is a unary operation that performs logical negation on each bit, forming the ones’
 * complement of the given binary value. Bits that are 0 become 1, and those that are 1 become 0.
 */
function bitNot(int|float|Query $value): BitNot
{
    return new BitNot($value);
}

/**
 * In an arithmetic shift (also referred to as signed shift), like a logical shift, the bits that slide off the end
 * disappear (except for the last, which goes into the carry flag). But in an arithmetic shift, the spaces are
 * filled in such a way to preserve the sign of the number being slid. For this reason, arithmetic shifts are better
 * suited for signed numbers in two’s complement format.
 *
 * Note: SHL and SAL are the same, and differentiation only happens because SAR and SHR (right shifting) has
 * differences in their implementation.
 */
function bitSal(int|float|Query $left, int|float|Query ...$other): BitSal
{
    return new BitSal($left, $other);
}

/**
 * In an arithmetic shift (also referred to as signed shift), like a logical shift, the bits that slide off the end
 * disappear (except for the last, which goes into the carry flag). But in an arithmetic shift, the spaces are
 * filled in such a way to preserve the sign of the number being slid. For this reason, arithmetic shifts are better
 * suited for signed numbers in two’s complement format.
 */
function bitSar(int|float|Query $left, int|float|Query ...$other): BitSar
{
    return new BitSar($left, $other);
}

/**
 * Generate a random number between given (or implied) bounds. random takes zero, one or two arguments.
 *
 * - With zero arguments, the result will be a floating-point number in the range [0,1) (from 0 up to but not including
 *   1).
 * - With one argument x, the result will be in the range [0,x), and will be integer unless {float: true} is given as
 *   an option. Specifying a floating point number without the float option will raise an error.
 * - With two arguments x and y, the result will be in the range [x,y), and will be integer unless {float: true} is
 *   given as an option. If x and y are equal an error will occur, unless the floating-point option has been specified,
 *   in which case x will be returned. Specifying a floating point number without the float option will raise an error.
 * Note: The last argument given will always be the ‘open’ side of the range, but when generating a floating-point
 * number, the ‘open’ side may be less than the ‘closed’ side.
 *
 * @see https://rethinkdb.com/api/javascript/random/
 */
function random(
    int|float|Query|null $left = null,
    int|float|Query|RandomOptions|null $right = null,
    ?RandomOptions $opts = null
): Random {
    return new Random($left, $right, $opts);
}

/**
 * Return a time object representing the current time in UTC. The command now() is computed once when the server
 * receives the query, so multiple instances of r.now() will always return the same time inside a query.
 */
function now(): Now
{
    return new Now();
}

/**
 * Create a time object for a specific time.
 *
 * A few restrictions exist on the arguments:
 *
 * - year is an integer between 1400 and 9,999.
 * - month is an integer between 1 and 12.
 * - day is an integer between 1 and 31.
 * - hour is an integer.
 * - minutes is an integer.
 * - seconds is a double. Its value will be rounded to three decimal places (millisecond-precision).
 * - timezone can be 'Z' (for UTC) or a string with the format ±[hh]:[mm].
 */
function time(
    int|Query $year,
    int|Query $month,
    int|Query $day,
    string|int|Query|null $hourOrTimezone = null,
    string|int|Query|null $minute = null,
    string|int|Query|null $second = null,
    string|int|Query|null $timezone = null
): Time {
    return new Time($year, $month, $day, $hourOrTimezone, $minute, $second, $timezone);
}

/**
 * Create a time object based on seconds since epoch. The first argument is a double and will be rounded to three
 * decimal places (millisecond-precision).
 */
function epochTime(int|float|Query $epochTime): EpochTime
{
    return new EpochTime($epochTime);
}

/**
 * Create a time object based on an ISO 8601 date-time string (e.g. ‘2013-01-01T01:01:01+00:00’). RethinkDB supports
 * all valid ISO 8601 formats except for week dates. Read more about the ISO 8601 format at Wikipedia.
 *
 * If you pass an ISO 8601 string without a time zone, you must specify the time zone with the defaultTimezone argument.
 */
function iso8601(string|Query $iso8601Date, Iso8601Options $opts = new Iso8601Options()): Iso8601
{
    return new Iso8601($iso8601Date, $opts);
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function monday(): Monday
{
    return new Monday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function tuesday(): Tuesday
{
    return new Tuesday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function wednesday(): Wednesday
{
    return new Wednesday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function thursday(): Thursday
{
    return new Thursday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function friday(): Friday
{
    return new Friday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function saturday(): Saturday
{
    return new Saturday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function sunday(): Sunday
{
    return new Sunday();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function january(): January
{
    return new January();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function february(): February
{
    return new February();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function march(): March
{
    return new March();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function april(): April
{
    return new April();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function may(): May
{
    return new May();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function june(): June
{
    return new June();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function july(): July
{
    return new July();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function august(): August
{
    return new August();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function september(): September
{
    return new September();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function october(): October
{
    return new October();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function november(): November
{
    return new November();
}

/**
 * @see https://rethinkdb.com/docs/dates-and-times/java/#retrieving-portions-of-times
 */
function december(): December
{
    return new December();
}

/**
 * Convert a GeoJSON object to a ReQL geometry object.
 *
 * RethinkDB only allows conversion of GeoJSON objects which have ReQL equivalents: Point, LineString, and Polygon.
 * MultiPoint, MultiLineString, and MultiPolygon are not supported. (You could, however, store multiple points, lines
 * and polygons in an array and use a geospatial multi index with them.)
 *
 * Only longitude/latitude coordinates are supported. GeoJSON objects that use Cartesian coordinates, specify an
 * altitude, or specify their own coordinate reference system will be rejected.
 *
 * @see https://rethinkdb.com/api/javascript/geoJSON
 */
function geoJSON(array|object $geojson): GeoJSON
{
    return new GeoJSON($geojson);
}

/**
 * Construct a geometry object of type Point. The point is specified by two floating point numbers, the
 * longitude (−180 to 180) and latitude (−90 to 90) of the point on a perfect sphere. See Geospatial support for more
 * information on ReQL’s coordinate system.
 *
 * @see https://rethinkdb.com/api/javascript/point
 */
function point(int|float|Query $lat, int|float|Query $lon): Point
{
    return new Point($lat, $lon);
}

/**
 * Construct a geometry object of type Line. The line can be specified in one of two ways:
 *
 * - Two or more two-item arrays, specifying latitude and longitude numbers of the line’s vertices;
 * - Two or more Point objects specifying the line’s vertices.
 * Longitude (−180 to 180) and latitude (−90 to 90) of vertices are plotted on a perfect sphere. See Geospatial support
 * for more information on ReQL’s coordinate system.
 *
 * @see https://rethinkdb.com/api/javascript/line
 */
function line(array|Query ...$points): Line
{
    return new Line(...$points);
}

/**
 * Construct a geometry object of type Polygon. The Polygon can be specified in one of two ways:
 *
 * - Three or more two-item arrays, specifying latitude and longitude numbers of the polygon’s vertices;
 * - Three or more Point objects specifying the polygon’s vertices.
 * Longitude (−180 to 180) and latitude (−90 to 90) of vertices are plotted on a perfect sphere. See Geospatial
 * support for more information on ReQL’s coordinate system.
 *
 * If the last point does not specify the same coordinates as the first point, polygon will close the polygon by
 * connecting them. You cannot directly construct a polygon with holes in it using polygon, but you can use polygonSub
 * to use a second polygon within the interior of the first to define a hole.
 *
 * @see https://rethinkdb.com/api/javascript/polygon
 */
function polygon(array|Query ...$points): Polygon
{
    return new Polygon(...$points);
}

/**
 * Construct a circular line or polygon. A circle in RethinkDB is a polygon or line approximating a circle of a given
 * radius around a given center, consisting of a specified number of vertices (default 32).
 *
 * The center may be specified either by two floating point numbers, the latitude (−90 to 90) and
 * longitude (−180 to 180) of the point on a perfect sphere (see Geospatial support for more information on ReQL’s
 * coordinate system), or by a point object. The radius is a floating point number whose units are meters by default,
 * although that may be changed with the unit argument.
 */
function circle(array|Query $center, int|float|Query $radius, CircleOptions $opts = new CircleOptions()): Circle
{
    return new Circle($center, $radius, $opts);
}

/**
 * Tests whether two geometry objects intersect with one another. When applied to a sequence of geometry objects,
 * intersects acts as a filter, returning a sequence of objects from the sequence that intersect with the argument.
 */
function intersects(Query $g1, Query $g2): Intersects
{
    return new Intersects($g1, $g2);
}

/**
 * Compute the distance between a point and another geometry object. At least one of the geometry objects specified
 * must be a point.
 *
 * If one of the objects is a polygon or a line, the point will be projected onto the line or polygon assuming a
 * perfect sphere model before the distance is computed (using the model specified with geoSystem). As a consequence,
 * if the polygon or line is extremely large compared to Earth’s radius and the distance is being computed with the
 * default WGS84 model, the results of distance should be considered approximate due to the deviation between the
 * ellipsoid and spherical models.
 */
function distance(Query $g1, Query $g2, DistanceOptions $opts = new DistanceOptions()): Distance
{
    return new Distance($g1, $g2, $opts);
}

/**
 * Return a UUID (universally unique identifier), a string that can be used as a unique ID. If a string is passed to
 * uuid as an argument, the UUID will be deterministic, derived from the string’s SHA-1 hash.
 *
 * RethinkDB’s UUIDs are standards-compliant. Without the optional argument, a version 4 random UUID will be
 * generated; with that argument, a version 5 UUID will be generated, using a fixed namespace UUID of
 * 91461c99-f89d-49d2-af96-d8e2e14e9b58. For more information, read Wikipedia’s UUID article.
 *
 * Please take into consideration when you generating version 5 UUIDs can’t be considered guaranteed unique if they’re
 * computing based on user data because they use SHA-1 algorithm.
 */
function uuid(string|Query|null $str = null): Uuid
{
    return new Uuid($str);
}

/**
 * @see https://rethinkdb.com/api/javascript/between/
 */
function minval(): Minval
{
    return new Minval();
}

/**
 * @see https://rethinkdb.com/api/javascript/between/
 */
function maxval(): Maxval
{
    return new Maxval();
}

/**
 * Generate a stream of sequential integers in a specified range.
 *
 * range takes 0, 1 or 2 arguments:
 *
 * - With no arguments, range returns an “infinite” stream from 0 up to and including the maximum integer value;
 * - With one argument, range returns a stream from 0 up to but not including the end value;
 * - With two arguments, range returns a stream from the start value up to but not including the end value.
 * Note that the left bound (including the implied left bound of 0 in the 0- and 1-argument form) is always closed and
 * the right bound is always open: the start value will always be included in the returned range and the end value will
 * not be included in the returned range.
 *
 * Any specified arguments must be integers, or a ReqlRuntimeError will be thrown. If the start value is equal or to
 * higher than the end value, no error will be thrown but a zero-element stream will be returned.
 */
function range(int|Query|null $startOrEndValue = null, int|Query|null $endValue = null): Range
{
    return new Range($startOrEndValue, $endValue);
}

/**
 * Transform each element of one or more sequences by applying a mapping function to them. If map is run with two or
 * more sequences, it will iterate for as many items as there are in the shortest sequence.
 *
 * Note that map can only be applied to sequences, not single values. If you wish to apply a function to a single
 * value/selection (including an array), use the do command.
 */
function mapMultiple(array|Query $sequences, callable|Query|array ...$mappingFunction): MapMultiple
{
    return new MapMultiple($sequences, ...$mappingFunction);
}

/**
 * Merge two or more sequences.
 *
 * @see https://rethinkdb.com/api/javascript/union
 */
function union(array|Query $sequence, array|Query|UnionOptions ...$otherSequences): Union
{
    return new Union($sequence, ...$otherSequences);
}

/**
 * Rounds the given value up, returning the smallest integer value greater than or equal to the given value (the
 * value’s ceiling).
 */
function ceil(float|int|Query $value): Ceil
{
    return new Ceil($value);
}

/**
 * Rounds the given value down, returning the largest integer value less than or equal to the given value (the value’s
 * floor).
 */
function floor(float|int|Query $value): Floor
{
    return new Floor($value);
}

/**
 * Rounds the given value to the nearest whole integer.
 */
function round(float|int|Query $value): Round
{
    return new Round($value);
}

function systemInfo(): string
{
    return 'PHP-RQL Version: '.PHP_RQL_VERSION."\n";
}
