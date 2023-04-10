<?hh // strict

namespace Slack\SQLFake;

use function Facebook\FBExpect\expect;
use type Facebook\HackTest\HackTest;

final class DeleteQueryValidatorTest extends HackTest {
    private static ?AsyncMysqlConnection $conn;

    <<__Override>>
    public static async function beforeFirstTestAsync(): Awaitable<void> {
        static::$conn = await SharedSetup::initVitessAsync();
        // block hole logging
        // ? copied from SelectQueryValidatorTest.php, not sure what that means.
        Logger::setHandle(new \HH\Lib\IO\MemoryHandle());
    }

    <<__Override>>
    public async function beforeEachTestAsync(): Awaitable<void> {
        restore('vitess_setup');
        QueryContext::$strictSchemaMode = false;
        QueryContext::$strictSQLMode = false;
    }

    public async function testDeleteWithoutLimit(): Awaitable<void> {
        $conn = static::$conn as nonnull;

        expect(() ==> $conn->query('delete vt_table1 where id = 3'))->toThrow(
            SQLFakeVitessQueryViolation::class,
            'Vitess query validation error: unsupported: updates need a limit'
        );
    }

    public async function testDeleteWithHighLimit(): Awaitable<void> {
        $conn = static::$conn as nonnull;

        expect(() ==> $conn->query('delete vt_table1 where id = 3 LIMIT 501'))->toThrow(
            SQLFakeVitessQueryViolation::class,
            'Vitess query validation error: unsupported: updates cannot update more than 500 rows'
        );
    }
}
