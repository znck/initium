<?php namespace Znck\Initium;

class VariableReplacer
{
    /**
     * @var string
     */
    private $projectDirectory;

    public function __construct(string $projectDirectory) {
        $this->projectDirectory = $projectDirectory;
    }

    public function extractVariables() {
        $variables = [];
        $this->operateOnAllFiles($variables, 'extract');
        return $variables;
    }

    public function insertVariables(array $variables) {
        $this->operateOnAllFiles($variables, 'insert');
        return $variables;
    }

    protected function extract(string $file, array &$variables) {
        $subject = file_get_contents($file);
        preg_match_all('/:([A-Za-z0-9_]+)[^A-Za-z0-9_]/', $subject, $matches);
        foreach ($matches[1] as $match) {
            $variables[mb_strtolower($match)] = ucwords(str_replace('_', ' ', $match));
        }
    }

    protected function insert(string $file, $variables) {
        $subject = file_get_contents($file);
        foreach ($variables as $variable => $value) {
            $subject = str_replace(
                [':'.$variable, ':'.ucfirst($variable), ':'.str_replace(' ', '_', ucwords(str_replace('_', ' ', $variable))), ':'.mb_strtoupper($variable)],
                [$value, ucfirst($value), ucwords($variable), mb_strtoupper($value)],
                $subject
            );
        }
        file_put_contents($file, $subject);
    }

    protected function operateOnAllFiles(array &$variables, string $callback) {
        $directories = [$this->projectDirectory];

        while (count($directories)) {
            $directory = array_shift($directories);
            $files = glob($directory.DIRECTORY_SEPARATOR.'*');
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $directories[] = $file;
                } elseif (preg_match('/^text\/.*/', mime_content_type($file))) {
                    $this->$callback($file, $variables);
                }
            }
        }
    }
}
