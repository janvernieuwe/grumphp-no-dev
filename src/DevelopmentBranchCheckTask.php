<?php declare(strict_types=1);

namespace Janvernieuwe\DevBranchCheck;

use GrumPHP\Formatter\ProcessFormatterInterface;
use GrumPHP\Process\ProcessBuilder;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Config\ConfigOptionsResolver;
use GrumPHP\Task\Config\EmptyTaskConfig;
use GrumPHP\Task\Config\TaskConfigInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use GrumPHP\Task\TaskInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DevelopmentBranchCheckTask implements TaskInterface
{
    private EmptyTaskConfig|TaskConfigInterface $config;

    public function __construct(
        private ProcessBuilder $processBuilder,
        private ProcessFormatterInterface $formatter,
    ) {
        $this->config = new EmptyTaskConfig();
    }

    public static function getConfigurableOptions(): ConfigOptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'composer_file'    => 'composer.json',
            'triggered_by'     => ['composer.json', 'composer.lock', '*.php'],
            'allowed_packages' => [],
        ]);

        $resolver->addAllowedTypes('composer_file', ['string']);
        $resolver->addAllowedTypes('triggered_by', ['array']);
        $resolver->addAllowedTypes('allowed_packages', ['array']);

        return ConfigOptionsResolver::fromClosure(
            static fn(array $options): array => $resolver->resolve($options)
        );
    }

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof RunContext || $context instanceof GitPreCommitContext;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $arguments = $this->processBuilder->createArgumentsForCommand('composer');
        $arguments->add('show');
        $arguments->add('--working-dir=' . getcwd());
        $arguments->add('--format=json');
        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = $this->formatter->format($process);
            if($context instanceof GitPreCommitContext) {
                return TaskResult::createNonBlockingFailed($this, $context, $message);
            }
            return TaskResult::createFailed($this, $context, $message);
        }
        $output = $process->getOutput();
        $dependencies = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $devDependencies = array_filter(
            $dependencies['installed'],
            fn(array $dependency) => $this->validateDependency($dependency)
        );
        $devDependencies = array_map(
            static fn(array $dependency): string => $dependency['name'],
            $devDependencies
        );
        $allowedPackages = $this->config->getOptions()['allowed_packages'] ?? [];
        $notAllowed = array_diff($devDependencies, $allowedPackages);
        if (!count($notAllowed)) {
            return TaskResult::createPassed($this, $context);
        }
        $error = 'Following dev-* dependencies are not allowed: ' . implode(', ', $notAllowed);

        if($context instanceof GitPreCommitContext) {
            return TaskResult::createNonBlockingFailed($this, $context, $error);
        }
        return TaskResult::createFailed($this, $context, $error);
    }
    private function validateDependency(array $dependency): bool
    {
        return str_starts_with($dependency['version'], 'dev-') && $dependency['direct-dependency'];
    }

    public function getConfig(): TaskConfigInterface
    {
        return $this->config;
    }

    public function withConfig(TaskConfigInterface $config): TaskInterface
    {
        $new = clone $this;
        $new->config = $config;

        return $new;
    }
}
