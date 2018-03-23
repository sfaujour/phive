<?php
namespace PharIo\Phive\RegressionTests;

use PharIo\FileSystem\Filename;
use PharIo\Phive\Cli\Runner;
use PharIo\Phive\ConfiguredPhar;
use PharIo\Phive\InstalledPhar;
use PharIo\Phive\PharAlias;
use PharIo\Phive\PharUrl;
use PharIo\Phive\RequestedPhar;
use PharIo\Version\AnyVersionConstraint;
use PharIo\Version\ExactVersionConstraint;
use PharIo\Version\Version;

class InstallCommandTest extends RegressionTestCase {

    public function testInstallsPhar() {
        $this->addPharToRegistry('phpunit', '5.3.1', 'phpunit-5.3.1.phar');

        $this->runPhiveCommand('install', ['phpunit@5.3.1']);

        $this->assertSymlinkTargetEquals(
            $this->getToolsDirectory()->file('phpunit'),
            $this->getPhiveHomeDirectory()->child('phars')->file('phpunit-5.3.1.phar')->asString()
        );
    }

    public function testCopiesPhar() {
        $this->addPharToRegistry('phpunit', '5.3.1', 'phpunit-5.3.1.phar');

        $this->runPhiveCommand('install', ['--copy', 'phpunit@5.3.1']);

        $target = $this->getToolsDirectory()->file('phpunit')->asString();

        $this->assertFileIsNotASymlink($target);

        $this->assertFileEquals(
            $this->getPhiveHomeDirectory()->child('phars')->file('phpunit-5.3.1.phar')->asString(),
            $target
        );
    }

    public function testAddsPharNodeToPhiveXmlConfig() {
        $phiveXmlConfig = $this->getPhiveXmlConfig();
        $this->addPharToRegistry('phpunit', '5.3.1', 'phpunit-5.3.1.phar');

        $this->assertEmpty($phiveXmlConfig->getPhars());

        $this->runPhiveCommand('install', ['phpunit@5.3.1']);

        // config needs to be reloaded
        $phiveXmlConfig = $this->getPhiveXmlConfig();

        $expectedPhars = [
            new ConfiguredPhar('phpunit', new ExactVersionConstraint('5.3.1'), new Version('5.3.1'), new Filename('./tools/phpunit'))
        ];

        $this->assertEquals($expectedPhars, $phiveXmlConfig->getPhars());
    }

    public function testThrowsErrorIfGlobalAndTargetOptionsAreCombined() {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(Runner::RC_PARAM_ERROR);

        $this->runPhiveCommand('install', ['--global', '--target tools', 'phpunit']);
    }

    public function testLinksPharToLocationConfiguredInPhiveXml()
    {
        $this->addPharToRegistry('phpunit', '5.3.1', 'phpunit-5.3.1.phar');

        $phiveXmlConfig = $this->getPhiveXmlConfig();
        $phiveXmlConfig->addPhar(
            new InstalledPhar(
                'phpunit',
                new Version('5.3.1'),
                new AnyVersionConstraint(),
                new Filename('./foo/tests'),
                false
            ),
            new RequestedPhar(
                new PharAlias('phpunit'),
                new AnyVersionConstraint(),
                new AnyVersionConstraint()
            )
        );

        $this->runPhiveCommand('install');

        $this->assertFileNotExists($this->getWorkingDirectory()->child('tools')->file('phpunit')->asString());
        $this->assertFileExists($this->getWorkingDirectory()->child('foo')->file('tests')->asString());
    }

    public function testAddsSourceUrlToPhiveXml()
    {
        $this->runPhiveCommand('install', ['https://phar.phpunit.de/test-mapper-1.0.0.phar']);

        $config = $this->getPhiveXmlConfig();

        $this->assertTrue($config->hasConfiguredPhar('test-mapper', new Version('1.0.0')));

        $phar = $config->getConfiguredPhar('test-mapper', new Version('1.0.0'));

        $this->assertTrue($phar->hasUrl());
        $this->assertEquals(new PharUrl('https://phar.phpunit.de/test-mapper-1.0.0.phar'), $phar->getUrl());
    }

}
