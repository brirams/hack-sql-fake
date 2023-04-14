<?hh // strict

namespace Slack\SQLFake;

use namespace HH\Lib\Str;

/**
 * The query running interface
 * This parses a SQL statement using the Parser, then takes the parsed Query representation and executes it
 */
final class SQLCommandProcessor {

  public static function execute(string $sql, AsyncMysqlConnection $conn): (dataset, int) {

    // Check for unsupported statements
    if (Str\starts_with_ci($sql, 'SET') || Str\starts_with_ci($sql, 'BEGIN') || Str\starts_with_ci($sql, 'COMMIT')) {
      // we don't do any handling for these kinds of statements currently
      return tuple(vec[], 0);
    }

    if (Str\starts_with_ci($sql, 'ROLLBACK')) {
      // unlike BEGIN and COMMIT, this actually needs to have material effect on the observed behavior
      // even in a single test case, and so we need to throw since it's not implemented yet
      // there's no reason we couldn't start supporting transactions in the future, just haven't done the work yet
      throw new SQLFakeNotImplementedException('Transactions are not yet supported');
    }

    $query = SQLParser::parse($sql);

    $is_vitess_query = $conn->getServer()->config['is_vitess'] ?? false;
    // TODO: We need to have a more granular way  to tell `hack-mysql-fake` what rules need to be applied to a query since we're
    // using Vitess across the board but with different runtime characteristics:
    //  - "vitess" should have all rules applied but we need to have a way to opt checks in on a per query level
    //  - "vifL" and "auxN" should have all the checks enabled that don't involve a vschema because vifl doesn't have a vschema
    // QueryContext seems to be the place to put this, as we can set per table rules but is that granular enough to allow for cases
    // where a callsite is querying multiple tables but I want to override certain rules for a test run?
    if ($is_vitess_query && !QueryContext::$skipVitessValidation) {
      VitessQueryValidator::validate($query, $conn);
    }

    if ($query is SelectQuery) {
      return tuple($query->execute($conn), 0);
    } else if ($query is UpdateQuery) {
      return tuple(vec[], $query->execute($conn));
    } else if ($query is DeleteQuery) {
      return tuple(vec[], $query->execute($conn));
    } else if ($query is InsertQuery) {
      return tuple(vec[], $query->execute($conn));
    } else {
      throw new SQLFakeNotImplementedException('Unhandled query type: '.\get_class($query));
    }
  }
}
