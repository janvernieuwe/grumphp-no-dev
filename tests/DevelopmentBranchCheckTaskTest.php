<?php

namespace Janvernieuwe\DevBranchCheck\Tests;

use GrumPHP\Formatter\ProcessFormatterInterface;
use GrumPHP\Process\ProcessBuilder;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Config\ConfigOptionsResolver;
use GrumPHP\Task\Config\Metadata;
use GrumPHP\Task\Config\TaskConfig;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DevelopmentBranchCheckTaskTest extends TestCase
{
    private MockObject $processBuilder;
    private MockObject $formatter;
    private DevelopmentBranchCheckTask $task;
    private DevelopmentBranchCheckTask $developmentBranchCheckTask;

    protected function setUp(): void
    {
        $this->processBuilder = $this->getMockBuilder(ProcessBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->formatter = $this->getMockBuilder(ProcessFormatterInterface::class)->getMock();
        $this->task = new DevelopmentBranchCheckTask($this->processBuilder, $this->formatter);

        $processBuilderMock = $this->createMock(ProcessBuilder::class);
        $processFormatterMock = $this->createMock(ProcessFormatterInterface::class);

        $this->developmentBranchCheckTask = new DevelopmentBranchCheckTask($processBuilderMock, $processFormatterMock);
    }

    /**
     * Testing the normal scenario where process is running successfully and there aren't any forbidden dependencies
     * @covers  \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::run
     */
    public function testRunPass(): void
    {
        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $output = [
            'installed' => [
                [
                    'version'           => 'dev-master',
                    'direct-dependency' => true,
                    'name'              => 'allowed/dev-package',
                ],
            ],
        ];
        $process->method('getOutput')->willReturn(json_encode($output, JSON_THROW_ON_ERROR));

        $this->processBuilder->expects($this->once())->method('buildProcess')->willReturn($process);

        $this->task = $this->task->withConfig(new TaskConfig(
            name: 'janvernieuwe_dev_branch',
            options: ['allowed_packages' => ['allowed/dev-package']],
            metadata: new Metadata([])
        ));

        $context = $this->createMock(GitPreCommitContext::class);
        $result = $this->task->run($context);

        $this->assertInstanceOf(TaskResultInterface::class, $result);
        $this->assertTrue($result->isPassed());
    }

    /**
     * Test whether the process fails as expected when an unofficial dev dependency exists
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::run
     */
    public function testRunFail(): void
    {
        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $output = [
            'installed' => [
                [
                    'version'           => 'dev-master',
                    'direct-dependency' => true,
                    'name'              => 'disallowed/dev-package',
                ],
                [
                    'version'           => 'dev-master',
                    'direct-dependency' => true,
                    'name'              => 'allowed/dev-package',
                ],
            ],
        ];
        $process->method('getOutput')->willReturn(json_encode($output, JSON_THROW_ON_ERROR));

        $this->processBuilder->expects($this->once())->method('buildProcess')->willReturn($process);

        $this->task = $this->task->withConfig(new TaskConfig(
            name: 'janvernieuwe_dev_branch',
            options: ['allowed_packages' => ['allowed/dev-package']],
            metadata: new Metadata([])
        ));

        $context = $this->createMock(GitPreCommitContext::class);
        $result = $this->task->run($context);
        $this->assertInstanceOf(TaskResultInterface::class, $result);
        $this->assertTrue($result->hasFailed());
        $this->assertEquals(TaskResult::FAILED, $result->getResultCode());

    }

    /**
     * Test whether the process fails as expected when an unofficial dev dependency exists
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::run
     */
    public function testRunNoFailOnCommit(): void
    {
        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $output = [
            'installed' => [
                [
                    'version'           => 'dev-master',
                    'direct-dependency' => true,
                    'name'              => 'disallowed/dev-package',
                ],
                [
                    'version'           => 'dev-master',
                    'direct-dependency' => true,
                    'name'              => 'allowed/dev-package',
                ],
            ],
        ];
        $process->method('getOutput')->willReturn(json_encode($output, JSON_THROW_ON_ERROR));

        $this->processBuilder->expects($this->once())->method('buildProcess')->willReturn($process);

        $this->task = $this->task->withConfig(new TaskConfig(
            name: 'janvernieuwe_dev_branch',
            options: ['allowed_packages' => ['allowed/dev-package'], 'fail_on_commit' => false],
            metadata: new Metadata([])
        ));

        $context = $this->createMock(GitPreCommitContext::class);
        $result = $this->task->run($context);
        $this->assertInstanceOf(TaskResultInterface::class, $result);
        $this->assertEquals(TaskResult::NONBLOCKING_FAILED, $result->getResultCode());
    }


    /**
     * Test whether the process fails as expected when the process fails
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::run
     */
    public function testGetConfigurableOptions(): void
    {
        $result = $this->developmentBranchCheckTask::getConfigurableOptions();

        $this->assertInstanceOf(ConfigOptionsResolver::class, $result);

        $configurableOptions = $result->resolve([]);
        $this->assertTrue(is_array($configurableOptions));
        $this->assertArrayHasKey('composer_file', $configurableOptions);
        $this->assertArrayHasKey('triggered_by', $configurableOptions);
        $this->assertArrayHasKey('allowed_packages', $configurableOptions);
        $this->assertArrayHasKey('fail_on_commit', $configurableOptions);
        $this->assertSame('composer.json', $configurableOptions['composer_file']);
        $this->assertSame(['composer.json', 'composer.lock', '*.php'], $configurableOptions['triggered_by']);
        $this->assertSame([], $configurableOptions['allowed_packages']);
        $this->assertSame(true, $configurableOptions['fail_on_commit']);
    }


    /**
     * Test validateDependency method when the dependency version starts with 'dev-' and it's a direct dependency
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::validateDependency
     */
    public function testValidateDependencyWithDevVersionAndDirectDependency(): void
    {
        $dependency = ['version' => 'dev-master', 'direct-dependency' => true];
        $method = $this->getPrivateMethod('validateDependency');
        $result = $method->invokeArgs($this->task, [$dependency]);

        $this->assertTrue($result);
    }

    /**
     * Test validateDependency method when the dependency version does not start with 'dev-'
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::validateDependency
     */
    public function testValidateDependencyWithoutDevVersion(): void
    {
        $dependency = ['version' => '1.0.1', 'direct-dependency' => true];
        $method = $this->getPrivateMethod('validateDependency');
        $result = $method->invokeArgs($this->task, [$dependency]);

        $this->assertFalse($result);
    }

    /**
     * Test validateDependency method when it's not a direct dependency
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::validateDependency
     */
    public function testValidateDependencyWithoutDirectDependency(): void
    {
        $dependency = ['version' => 'dev-master', 'direct-dependency' => false];
        $method = $this->getPrivateMethod('validateDependency');
        $result = $method->invokeArgs($this->task, [$dependency]);

        $this->assertFalse($result);
    }

    /**
     * Function to get private class method to allow testing
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::validateDependency
     */
    private function getPrivateMethod(string $name): \ReflectionMethod
    {
        $class = new \ReflectionClass('Janvernieuwe\\DevBranchCheck\\DevelopmentBranchCheckTask');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }


    /**
     * This method tests if canRunInContext can operate with GitPreCommitContext
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::canRunInContext
     */
    public function testCanRunInGitPreCommitContext(): void
    {
        $contextMock = $this->getMockBuilder(GitPreCommitContext::class)->disableOriginalConstructor()->getMock();

        $canRun = $this->task->canRunInContext($contextMock);
        $this->assertEquals(true, $canRun);
    }

    /**
     * This method tests if canRunInContext can operate with RunContext
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::canRunInContext
     */
    public function testCanRunInRunContext(): void
    {
        $contextMock = $this->getMockBuilder(RunContext::class)->disableOriginalConstructor()->getMock();

        $canRun = $this->task->canRunInContext($contextMock);
        $this->assertEquals(true, $canRun);
    }

    /**
     * This method tests if canRunInContext can operate with an inappropriate context
     * @covers \Janvernieuwe\DevBranchCheck\DevelopmentBranchCheckTask::canRunInContext
     */
    public function testCannotRunInInappropriateContext(): void
    {
        $contextMock = $this->getMockBuilder(ContextInterface::class)->getMock();

        $canRun = $this->task->canRunInContext($contextMock);
        $this->assertEquals(false, $canRun);
    }
}