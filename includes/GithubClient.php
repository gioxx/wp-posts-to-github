<?php

namespace POTOGH;

class GithubClient
{
    private string $token;
    private string $ownerRepo;
    private string $branch;

    public function __construct(string $token, string $ownerRepo, string $branch)
    {
        $this->token = $token;
        $this->ownerRepo = $ownerRepo;
        $this->branch = $branch;
    }

    public function getFile(string $path): ?array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/contents/%s?ref=%s',
            $this->ownerRepo,
            ltrim($path, '/'),
            $this->branch
        );

        $response = wp_remote_get($url, ['headers' => $this->headers()]);

        if (is_wp_error($response)) {
            return null;
        }

        if (wp_remote_retrieve_response_code($response) === 404) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ['sha' => $body['sha'] ?? null];
    }

    public function testConnection(): array
    {
        $repoUrl = sprintf('https://api.github.com/repos/%s', $this->ownerRepo);
        $repoResponse = wp_remote_get($repoUrl, ['headers' => $this->headers()]);

        if (is_wp_error($repoResponse)) {
            return ['success' => false, 'message' => $repoResponse->get_error_message()];
        }

        $repoCode = wp_remote_retrieve_response_code($repoResponse);

        if ($repoCode === 401) {
            return ['success' => false, 'message' => __('Invalid GitHub token.', 'post-to-github-md')];
        }

        if ($repoCode === 404) {
            return ['success' => false, 'message' => __('Repository not found or not accessible with this token.', 'post-to-github-md')];
        }

        if ($repoCode < 200 || $repoCode >= 300) {
            $body = json_decode(wp_remote_retrieve_body($repoResponse), true);
            $error = $body['message'] ?? sprintf(__('GitHub API error, HTTP %d.', 'post-to-github-md'), $repoCode);

            return ['success' => false, 'message' => $error];
        }

        $branchUrl = sprintf('https://api.github.com/repos/%s/branches/%s', $this->ownerRepo, rawurlencode($this->branch));
        $branchResponse = wp_remote_get($branchUrl, ['headers' => $this->headers()]);

        if (is_wp_error($branchResponse)) {
            return ['success' => false, 'message' => $branchResponse->get_error_message()];
        }

        $branchCode = wp_remote_retrieve_response_code($branchResponse);

        if ($branchCode === 404) {
            return ['success' => false, 'message' => sprintf(__('Branch "%s" not found in repository.', 'post-to-github-md'), $this->branch)];
        }

        if ($branchCode < 200 || $branchCode >= 300) {
            return ['success' => false, 'message' => sprintf(__('GitHub API error, HTTP %d.', 'post-to-github-md'), $branchCode)];
        }

        return ['success' => true, 'message' => __('Connection successful: repository and branch are reachable.', 'post-to-github-md')];
    }

    public function putFile(string $path, string $content, string $message, ?string $sha = null): array
    {
        $url = sprintf('https://api.github.com/repos/%s/contents/%s', $this->ownerRepo, ltrim($path, '/'));

        $payload = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $this->branch,
        ];

        if ($sha !== null) {
            $payload['sha'] = $sha;
        }

        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'headers' => $this->headers(),
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message(), 'status' => null];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'sha' => $body['content']['sha'] ?? null];
        }

        $error = $body['message'] ?? ('GitHub API error, HTTP ' . $code);

        if ($code === 409) {
            $error .= ' ' . __('(the file may have been modified directly on GitHub: check the repository contents before re-exporting)', 'post-to-github-md');
        }

        return [
            'success' => false,
            'error' => $error,
            'status' => $code,
        ];
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'post-to-github-md-wp-plugin',
        ];
    }
}
