<?php

namespace POTOGH;

class ExportService
{
    private Converter $converter;
    private GithubClient $githubClient;
    private string $baseFolder;

    public function __construct(Converter $converter, GithubClient $githubClient, string $baseFolder)
    {
        $this->converter = $converter;
        $this->githubClient = $githubClient;
        $this->baseFolder = $baseFolder;
    }

    public function exportPost(array $postData): array
    {
        $trace = [];

        $year = gmdate('Y', strtotime($postData['date_gmt']));
        $path = $postData['existing_path'] ?? PathBuilder::build($this->baseFolder, $year, $postData['slug']);
        $trace[] = sprintf('Percorso calcolato: %s', $path);

        $frontMatter = FrontMatter::build([
            'title' => $postData['title'],
            'slug' => $postData['slug'],
            'date' => $postData['date'],
            'modified' => $postData['modified'],
            'wp_id' => $postData['wp_id'],
            'categories' => $postData['categories'],
            'tags' => $postData['tags'],
            'permalink' => $postData['permalink'],
        ]);

        $markdown = $this->converter->convert($postData['content_html']);
        $fileContent = $frontMatter . "\n" . $markdown . "\n";
        $trace[] = 'Contenuto HTML convertito in Markdown.';

        $message = sprintf('Export post: %s (#%d)', $postData['title'], $postData['wp_id']);

        $trace[] = sprintf('Invio a GitHub in corso (%s)...', $path);
        $result = $this->githubClient->putFile($path, $fileContent, $message, $postData['existing_sha'] ?? null);

        if (!$result['success']) {
            $trace[] = sprintf('Errore da GitHub: %s', $result['error']);

            return ['success' => false, 'error' => $result['error'], 'trace' => $trace];
        }

        $trace[] = sprintf('File salvato su GitHub (sha: %s).', $result['sha']);

        return ['success' => true, 'path' => $path, 'sha' => $result['sha'], 'trace' => $trace];
    }
}
