<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

use Garden\Cli\Cli;

/**
 * Unit tests for the various command line interface classes.
 */
class CliTest extends PHPUnit_Framework_TestCase {
    /**
     * Test a cli run with no commands.
     *
     * @param array $argv The args to test.
     * @dataProvider provideBasicArgvs
     */
    public function testNoCommandParse(array $argv) {
        $cli = $this->getBasicCli();

        // Test some basic parsing scenarios.
        $parsed = $cli->parse($argv, false);

        $this->assertEquals([
            'hello' => 'world',
            'enabled' => true,
            'disabled' => false,
            'count' => 3
        ], $parsed->opts());
    }

    /**
     * Test a missing option.
     *
     * @expectedException \Exception
     * @expectedExceptionMessage Missing required option: hello
     */
    public function testMissingOpt() {
        $cli = $this->getBasicCli();

        $parsed = $cli->parse(['script', '-e'], false);
    }

    /**
     * Test invalid opt types.
     *
     * @param array $argv The arguments to parse.
     * @param string $message The expected exception message.
     * @dataProvider provideInvalidTypes
     */
    public function testInvalidTypes(array $argv, $message) {
        $this->setExpectedException('\Exception', $message, null);

        $cli = $this->getBasicCli();
        $parsed = $cli->parse($argv, false);
    }

    /**
     * Test a command line scheme created with {@link Cli::schema()}.
     */
    public function testSchema() {
        $cli = new Cli();
        $cli->schema([
            'hello',
            'b:enabled?' => 'Is it?',
            'i:count:c?' => 'How many?'
        ]);

        $parsed = $cli->parse(['script', '--hello=foo', '--enabled', '--count=123']);
        $this->assertEquals(['hello' => 'foo', 'enabled' => true, 'count' => 123], $parsed->opts());
    }

    /**
     * Get a sample {@link Cli} object with no commands for testing.
     *
     * @return Cli Returns the sample {@link Cli} instance.
     */
    protected function getBasicCli() {
        $cli = new Cli();

        $cli->opt('hello', 'Hello world.', true, 'string', 'h')
            ->opt('enabled', 'Enabled or not.', false, 'boolean', 'e')
            ->opt('disabled', 'Disabled or not', false, 'bool', 'd')
            ->opt('count', 'The count of things.', false, 'integer', 'c');

        return $cli;
    }

    /**
     * Provide some args for {@link testNoCommandParse()}.
     *
     * @return array Returns args for {@link testNoCommandParse()}.
     */
    public function provideBasicArgvs() {
        $result = [
            [['script', '--hello=world', '--enabled', '--disabled', 'false', '--count=3']],
            [['script', '-h', 'world', '-e', '-d', '0', '-c', '3']],
            [['script', '-hworld', '-e1', '-d0', '-ccc']],
            [['script', '--hello', 'world', '-ed0c2c']],
            [['script', 'filename', '--hello', 'world', '-c3', '--no-disabled', '-e']],
        ];
        return $result;
    }

    /**
     * Provide some args that should throw invalid type errors.
     *
     * @return array Returns an array suitable to be used as a data provider.
     */
    public function provideInvalidTypes() {
        $result = [
            [['script', '-hw', '--enabled=13'], 'The value of --enabled is not a valid boolean.'],
            [['script', '-hw', '--enabled=foo'], 'The value of --enabled is not a valid boolean.'],
            [['script', '-hw', '--no-enabled', 'foo'], 'The value of --no-enabled is not a valid boolean.'],
            [['script', '-hw', '--count=foo'], 'The value of --count is not a valid integer.'],
            [['script', '-hw', '--no-count=22'], 'Cannont apply the --no- prefix on the non boolean --count.'],
            [['script', '-hw', '-c', 'foo'], 'The value of --count (-c) is not a valid integer.'],
        ];

        return $result;
    }
}
