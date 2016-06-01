<?php namespace Znck\Initium;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use ZipArchive;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new project.')
            ->addArgument('name', InputArgument::REQUIRED, 'Project name')
            ->addArgument('source', InputArgument::OPTIONAL, 'Skeleton source repository')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->getSkeleton($input->getArgument('source') ?? 'znck/skeleton');

        $this->verifyProjectDoesNotExist(
            $directory = getcwd().'/'.$input->getArgument('name'),
            $output
        );

        $output->writeln('<info>Creating project...</info>');

        $version = $this->getVersion($input);

        $this->download($zipFile = $this->makeFilename(), $repository, $version)
            ->extract($zipFile, $directory)
            ->cleanUp($zipFile);

        $this->replaceVariables($directory, $input, $output);

        $composer = $this->findComposer();

        $commands = [
            $composer.' install --no-scripts',
        ];

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        $process->run(
            function ($type, $line) use ($output) {
                $output->write($line);
            }
        );

        $output->writeln('<comment>Project ready! Build something amazing.</comment>');
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param string $directory
     *
     * @return void
     */
    protected function verifyProjectDoesNotExist($directory, OutputInterface $output)
    {
        if (is_dir($directory)) {
            throw new RuntimeException('Project already exists!');
        }
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd().'/project_'.md5(time().uniqid()).'.zip';
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param string $zipFile
     * @param string $repository
     * @param string $version
     *
     * @return $this
     */
    protected function download($zipFile, $repository, $version = 'master')
    {
        $response = (new Client())->get("https://codeload.github.com/${repository}/zip/${version}");

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param string $zipFile
     * @param string $directory
     *
     * @return $this
     */
    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive();

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        $sub = glob($directory.DIRECTORY_SEPARATOR.'*');

        $temp = 'project_'.md5(time().uniqid());

        rename($sub[0], $temp);

        rmdir($directory);

        rename($temp, $directory);

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param string $zipFile
     *
     * @return $this
     */
    protected function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface
     *
     * @return string
     */
    protected function getVersion($input)
    {
        if ($input->getOption('dev')) {
            return 'develop';
        }

        return 'master';
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" composer.phar';
        }

        return 'composer';
    }

    protected function getSkeleton($param)
    {
        return count(explode('/', $param)) === 2 ? $param : 'znck/'.$param;
    }

    protected function replaceVariables(string $directory, InputInterface $input, OutputInterface $output)
    {
        $replacer = new VariableReplacer($directory);
        $variables = $replacer->extractVariables();

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $values = [];
        foreach ($variables as $key => $name) {
            $question = new Question("$name: ", $this->anticipateDefault($name));
            $answer = $helper->ask($input, $output, $question);
            $values[$key] = $answer;
        }
        $replacer->insertVariables($values);
    }

    protected function anticipateDefault($name)
    {
        // May be some variables can be auto-detected.
    }
}
