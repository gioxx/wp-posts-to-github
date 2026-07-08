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
            $error .= ' (il file potrebbe essere stato modificato direttamente su GitHub: verifica il contenuto del repository prima di ri-esportare)';
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
