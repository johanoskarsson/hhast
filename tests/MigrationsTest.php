<?hh // strict

/**
 * Copyright (c) 2016, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional
 * grant of patent rights can be found in the PATENTS file in the same
 * directory.
 *
 */

namespace Facebook\HHAST;

use function Facebook\FBExpect\expect;
use namespace Facebook\HHAST;
use namespace HH\Lib\{C, Str};

final class MigrationsTest extends TestCase {
  public function getMigrations(
  ): array<(classname<Migrations\BaseMigration>, string)> {
    return [
      tuple(
        Migrations\OptionalShapeFieldsMigration::class,
        'migrations/optional_shape_fields.php',
      ),
    ];
  }

  public function getMigrationSteps(
  ): array<(
    classname<Migrations\BaseMigration>,
    Migrations\IMigrationStep,
    string
  )> {
    $out = array();
    foreach ($this->getMigrations() as $row) {
      list($class, $fixture) = $row;
      foreach ((new $class())->getSteps() as $step) {
        $out[] = tuple(
          $class,
          $step,
          $fixture,
        );
      }
    }
    return $out;
  }


  /**
   * @dataProvider getMigrationSteps
   */
  public function testMigrationStepsAreIdempotent(
    classname<Migrations\BaseMigration> $migration,
    Migrations\IMigrationStep $step,
    string $fixture,
  ): void {
    $rewrite = $ast ==> $ast->rewrite(($n, $_) ==> $step->rewrite($n));

    $ast = HHAST\from_file(__DIR__.'/fixtures/'.$fixture.'.in')
      |> $rewrite($$);

    expect($rewrite($ast)->full_text())->toBeSame(
      $ast->full_text(),
      'Step "%s" in %s is not text-idempotent',
      $step->getName(),
      $migration,
    );

    // Equivalent to asserting the objects are the same, but gives useful output
    // if they differ
    if ($rewrite($ast) !== $ast) {
    //  var_dump("\n", $ast, $rewrite($ast));
    }
    expect($rewrite($ast))->toBeSame(
      $ast,
      'Step "%s" in %s is not object-idempotent',
      $step->getName(),
      $migration,
    );
  }

  /**
   * @dataProvider getMigrations
   */
  public function testMigrationHasExpectedOutput(
    classname<Migrations\BaseMigration> $migration,
    string $fixture,
  ): void {
    $ast = HHAST\from_file(__DIR__.'/fixtures/'.$fixture.'.in');

    $migration = new $migration();

    $ast = $migration->migrateAst($ast);

    $this->assertMatches(
      $ast->full_text(),
      $fixture.'.expect',
    );
  }

  /**
   * @dataProvider getMigrations
   */
  public function testMigrationIsIdempotent(
    classname<Migrations\BaseMigration> $migration,
    string $fixture,
  ): void {
    $ast = HHAST\from_file(__DIR__.'/fixtures/'.$fixture.'.in');

    $migration = new $migration();

    $ast = $migration->migrateAst($ast);

    expect(
      $migration->migrateAst($ast)->full_text()
    )->toBeSame(
      $ast->full_text(),
      'Migrating the AST twice should produce identical text to once',
    );

    expect(
      $migration->migrateAst($ast),
    )->toEqual(
      $ast,
      'Migrating the AST twice should get you an equal AST object',
    );

    //var_dump("\n", $ast, $migration->migrateAst($ast));

    expect(
      $migration->migrateAst($ast),
    )->toBeSame(
      $ast,
      'Migrating the AST twice should get you the same AST object',
    );
  }
}
