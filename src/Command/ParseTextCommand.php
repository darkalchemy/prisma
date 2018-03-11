<?php

namespace App\Command;

use Exception;
use FilesystemIterator;
use Gettext\Translations;
use MultipleIterator;
use Odan\Twig\TwigCompiler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Slim\Views\Twig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command.
 */
class ParseTextCommand extends AbstractCommand
{
    /**
     * @var OutputInterface
     */
    private $output;

    private $iterator;

    private $targets = [];

    private $regex;

    private $suffixes = [
        '.blade.php' => 'Blade',
        '.csv' => 'Csv',
        '.jed.json' => 'Jed',
        '.js' => 'JsCode',
        '.json' => 'Json',
        '.mo' => 'Mo',
        '.php' => ['PhpCode', 'PhpArray'],
        '.po' => 'Po',
        '.pot' => 'Po',
        '.twig' => 'Twig',
        '.xliff' => 'Xliff',
        '.yaml' => 'Yaml',
    ];

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->iterator = new MultipleIterator(MultipleIterator::MIT_NEED_ANY);
    }

    /**
     * Configure.
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('parse-text');
        $this->setDescription('Parse text');
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     *
     * @return int integer 0 on success, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $result = $this->compileTwig();
        if ($result) {
            return $result;
        }

        $result = $this->scanText();
        if ($result) {
            return $result;
        }

        return 0;
    }

    /**
     * Execute command.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     *
     * @return int integer 0 on success, or an error code
     */
    protected function compileTwig()
    {
        $this->output->write('Compiling Twig templates... ');

        /* @var Twig $twigView */
        $twigView = $this->container->get(Twig::class);

        $settings = $this->container->get('settings');
        $cachePath = $settings['twig']['cache_path'];

        // Get the Twig Environment instance from the Twig View instance
        $twig = $twigView->getEnvironment();
        $twig->setCache($cachePath);

        // Compile all Twig templates into cache directory
        $compiler = new TwigCompiler($twig, $cachePath);
        $compiler->compile();

        $this->output->write('<info>Done</info>', true);

        return 0;
    }

    /**
     * Execute command.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     *
     * @return int integer 0 on success, or an error code
     */
    protected function scanText()
    {
        $this->output->write('Scanning text...', true);

        $currentDir = getcwd();
        chdir(__DIR__ . '/../..');

        $this->extract('src')
            ->extract('tmp/twig-cache')
            ->extract('templates/', '/.*\.js/')
            ->extract('public/js/', '/.*\.js/')
            ->extract('templates/', '/.*\.js/')
            ->generate('resources/locale/de_DE_messages.po')
            ->generate('resources/locale/de_DE_messages.mo')
            ->process();

        $this->output->write('Done', true);

        chdir($currentDir);

        return 0;
    }

    /**
     * Run the task.
     */
    protected function process()
    {
        foreach ($this->targets as $targets) {
            $target = $targets[0];
            $translations = new Translations();
            $this->scan($translations);
            if (is_file($target)) {
                $fn = $this->getFunctionName('from', $target, 'File', 1);
                $translations->mergeWith(Translations::$fn($target));
            }
            foreach ($targets as $target) {
                $fn = $this->getFunctionName('to', $target, 'File', 1);
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $translations->$fn($target);

                $file = realpath($target);
                $this->output->write("Gettext exported to {$file}", true);
            }
        }

        $this->output->write('<info>All gettext generated successfuly!</info>', true);

        return 0;
    }

    /**
     * Execute the scan.
     *
     * @param Translations $translations
     */
    private function scan(Translations $translations)
    {
        foreach ($this->iterator as $each) {
            foreach ($each as $file) {
                if ($file === null || !$file->isFile()) {
                    continue;
                }
                $target = $file->getPathname();
                if (($fn = $this->getFunctionName('addFrom', $target, 'File'))) {
                    $translations->$fn($target);
                }
            }
        }
    }

    /**
     * Get the format based in the extension.
     *
     * @param string $prefix
     * @param string $file
     * @param string $suffix
     * @param int $key
     *
     * @return string|null
     */
    private function getFunctionName($prefix, $file, $suffix, $key = 0)
    {
        if (preg_match($this->getRegex(), strtolower($file), $matches)) {
            $format = $this->suffixes[$matches[1]];
            if (is_array($format)) {
                $format = $format[$key];
            }

            return sprintf('%s%s%s', $prefix, $format, $suffix);
        }

        return null;
    }

    /**
     * Returns the regular expression to detect the file format.
     *
     * @return string
     */
    private function getRegex()
    {
        if ($this->regex === null) {
            $this->regex = '/(' . str_replace('.', '\\.', implode('|', array_keys($this->suffixes))) . ')$/';
        }

        return $this->regex;
    }

    /**
     * Add a new target.
     *
     * @param string $path
     *
     * @return $this
     */
    public function generate($path)
    {
        $this->targets[] = func_get_args();

        return $this;
    }

    /**
     * Add a new source folder.
     *
     * @param string $path
     * @param null|string $regex
     *
     * @return $this
     */
    public function extract($path, $regex = null)
    {
        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);
        if ($regex) {
            $iterator = new RegexIterator($iterator, $regex);
        }
        $this->iterator->attachIterator($iterator);

        return $this;
    }
}