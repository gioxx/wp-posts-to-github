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
        $trace[] = sprintf(__('Computed path: %s', 'post-to-github-md'), $path);

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
        $trace[] = __('HTML content converted to Markdown.', 'post-to-github-md');

        $message = sprintf('Export post: %s (#%d)', $postData['title'], $postData['wp_id']);

        $trace[] = sprintf(__('Sending to GitHub (%s)...', 'post-to-github-md'), $path);
        $result = $this->githubClient->putFile($path, $fileContent, $message, $postData['existing_sha'] ?? null);

        if (!$result['success']) {
            $trace[] = sprintf(__('Error from GitHub: %s', 'post-to-github-md'), $result['error']);

            return [
                'success' => false,
                'error' => $result['error'],
                'trace' => $trace,
                'retry_after' => $result['retry_after'] ?? null,
            ];
        }

        $trace[] = sprintf(__('File saved on GitHub (sha: %s).', 'post-to-github-md'), $result['sha']);

        return ['success' => true, 'path' => $path, 'sha' => $result['sha'], 'trace' => $trace];
    }
}
