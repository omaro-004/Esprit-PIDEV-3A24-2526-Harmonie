<?php

$dir = __DIR__ . '/src/Repository';
$files = glob($dir . '/*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;

    $baseName = basename($file, '.php');
    $entityName = str_replace('Repository', '', $baseName);

    // Add generic types to class definition if missing
    if (strpos($content, '@extends ServiceEntityRepository') === false && strpos($content, 'class ' . $baseName . ' extends ServiceEntityRepository') !== false) {
        $content = str_replace(
            "class $baseName extends ServiceEntityRepository",
            "/**\n * @extends ServiceEntityRepository<$entityName>\n */\nclass $baseName extends ServiceEntityRepository",
            $content
        );
    }

    // Add return type annotations for arrays
    // Regex to find public function method(...): array and add PHPDoc if missing
    $content = preg_replace_callback(
        '/(?<!\*\/\n    )(public function [a-zA-Z0-9_]+\(.*?\): array)/s',
        function ($matches) use ($entityName) {
            $methodLine = $matches[1];
            // Depending on method name, we can guess the return type
            if (strpos($methodLine, 'Distribution') !== false || strpos($methodLine, 'Stats') !== false || strpos($methodLine, 'Trend') !== false) {
                return "/**\n     * @return array<mixed>\n     */\n    " . $methodLine;
            } else {
                return "/**\n     * @return {$entityName}[]\n     */\n    " . $methodLine;
            }
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Fixed " . basename($file) . "\n";
    }
}
