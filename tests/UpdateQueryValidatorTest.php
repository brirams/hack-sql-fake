<?hh // strict

namespace Slack\SQLFake;

use function Facebook\FBExpect\expect;
use type Facebook\HackTest\HackTest;

final class UpdateQueryValidatorTest extends HackTest {
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

    public async function testUpdateChangesPrimaryVindex(): Awaitable<void> {
        // this is disabled for now (but if Hack knows, it will emit errors because of coeffects)
        if ('always true, but opaque to Hack') {
            return;
        }
        $conn = static::$conn as nonnull;

        $unsupported_test_cases = vec[
            'update vt_table1 set id=1 where id=1',
            'update vt_table2 set vt_table1_id=1 where id=1',
        ];

        foreach ($unsupported_test_cases as $sql) {
            expect(() ==> $conn->query($sql))->toThrow(
                SQLFakeVitessQueryViolation::class,
                'Vitess query validation error: unsupported: update changes primary vindex column',
            );
        }

        $supported_test_cases = vec[
            "update vt_table1 set name='foo' where id = 1",
        ];

        foreach ($supported_test_cases as $sql) {
            expect(() ==> $conn->query($sql))->notToThrow(SQLFakeVitessQueryViolation::class);
        }

    }

    public async function testUnboundedUpdates(): Awaitable<void> {
        $conn = static::$conn as nonnull;

        $unboundedQuery = 'update vt_table1 set name=\'derp\' where id=1';
        expect(() ==> $conn->query($unboundedQuery))->toThrow(
            SQLFakeVitessQueryViolation::class,
            'Vitess query validation error: unsupported: updates need a limit',
        );

        $tooUnboundedQUery = 'update vt_table1 set name=\'derp\' where id = 2 LIMIT 501';
        expect(() ==> $conn->query($tooUnboundedQUery))->toThrow(
            SQLFakeVitessQueryViolation::class,
            'Vitess query validation error: unsupported: updates cannot update more than 500 rows',
        );
    }
}
