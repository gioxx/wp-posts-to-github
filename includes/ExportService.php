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
        $year = gmdate('Y', strtotime($postData['date_gmt']));
        $path = $postData['existing_path'] ?? PathBuilder::build($this->baseFolder, $year, $postData['slug']);

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

        $message = sprintf('Export post: %s (#%d)', $postData['title'], $postData['wp_id']);

        $result = $this->githubClient->putFile($path, $fileContent, $message, $postData['existing_sha'] ?? null);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'path' => $path, 'sha' => $result['sha']];
    }
}
