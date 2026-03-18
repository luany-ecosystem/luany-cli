<?php

namespace LuanyCli\Support;

class StubRenderer
{
    /**
     * Render a stub file by replacing all {{placeholders}} with values.
     *
     * @param array<string, string> $variables
     */
    public function render(string $stubPath, array $variables): string
    {
        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: [{$stubPath}]");
        }

        $content = file_get_contents($stubPath);

        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}
