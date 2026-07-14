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
        $repo = $this->fetchRepo();

        if (!$repo['success']) {
            return $repo;
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

    public function getDefaultBranch(): array
    {
        $repo = $this->fetchRepo();

        if (!$repo['success']) {
            return $repo;
        }

        $branch = $repo['body']['default_branch'] ?? null;

        if (!$branch) {
            return ['success' => false, 'message' => __('Could not determine the default branch.', 'post-to-github-md')];
        }

        return ['success' => true, 'branch' => $branch];
    }

    private function fetchRepo(): array
    {
        $url = sprintf('https://api.github.com/repos/%s', $this->ownerRepo);
        $response = wp_remote_get($url, ['headers' => $this->headers()]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 401) {
            return ['success' => false, 'message' => __('Invalid GitHub token.', 'post-to-github-md')];
        }

        if ($code === 404) {
            return ['success' => false, 'message' => __('Repository not found or not accessible with this token.', 'post-to-github-md')];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            $error = $body['message'] ?? sprintf(__('GitHub API error, HTTP %d.', 'post-to-github-md'), $code);

            return ['success' => false, 'message' => $error];
        }

        return ['success' => true, 'body' => $body];
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

        $retryAfter = $this->rateLimitRetryAfter($response, $code);

        if ($retryAfter !== null) {
            return [
                'success' => false,
                'error' => sprintf(__('GitHub rate limit reached. Retrying automatically in %d seconds.', 'post-to-github-md'), $retryAfter),
                'status' => $code,
                'retry_after' => $retryAfter,
            ];
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

    public function deleteFile(string $path, string $sha, string $message): array
    {
        $url = sprintf('https://api.github.com/repos/%s/contents/%s', $this->ownerRepo, ltrim($path, '/'));

        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => $this->headers(),
            'body' => wp_json_encode([
                'message' => $message,
                'sha' => $sha,
                'branch' => $this->branch,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return ['success' => true];
        }

        return $this->apiError($response, $code);
    }

    public function commitFiles(array $files, string $message): array
    {
        $ref = $this->getRef();

        if (!$ref['success'] && empty($ref['not_found'])) {
            return $ref;
        }

        $parentSha = null;
        $baseTreeSha = null;

        if ($ref['success']) {
            $parentSha = $ref['sha'];
            $commit = $this->getCommit($parentSha);

            if (!$commit['success']) {
                return $commit;
            }

            $baseTreeSha = $commit['tree_sha'];
        }

        $tree = $this->createTree($baseTreeSha, $files);

        if (!$tree['success']) {
            return $tree;
        }

        $commitResult = $this->createCommit($message, $tree['sha'], $parentSha);

        if (!$commitResult['success']) {
            return $commitResult;
        }

        $refResult = $this->updateRef($commitResult['sha'], $parentSha === null);

        if (!$refResult['success']) {
            return $refResult;
        }

        return [
            'success' => true,
            'commit_sha' => $commitResult['sha'],
            'blob_shas' => $tree['blob_shas'],
        ];
    }

    private function getRef(): array
    {
        $url = sprintf('https://api.github.com/repos/%s/git/ref/heads/%s', $this->ownerRepo, rawurlencode($this->branch));
        $response = wp_remote_get($url, ['headers' => $this->headers()]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 404) {
            return ['success' => false, 'not_found' => true];
        }

        if ($code < 200 || $code >= 300) {
            return $this->apiError($response, $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ['success' => true, 'sha' => $body['object']['sha'] ?? null];
    }

    private function getCommit(string $sha): array
    {
        $url = sprintf('https://api.github.com/repos/%s/git/commits/%s', $this->ownerRepo, $sha);
        $response = wp_remote_get($url, ['headers' => $this->headers()]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return $this->apiError($response, $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ['success' => true, 'tree_sha' => $body['tree']['sha'] ?? null];
    }

    private function createTree(?string $baseTreeSha, array $files): array
    {
        $payload = [
            'tree' => array_map(static function (array $file): array {
                return [
                    'path' => ltrim($file['path'], '/'),
                    'mode' => '100644',
                    'type' => 'blob',
                    'content' => $file['content'],
                ];
            }, $files),
        ];

        if ($baseTreeSha !== null) {
            $payload['base_tree'] = $baseTreeSha;
        }

        $url = sprintf('https://api.github.com/repos/%s/git/trees', $this->ownerRepo);
        $response = wp_remote_request($url, [
            'method' => 'POST',
            'headers' => $this->headers(),
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return $this->apiError($response, $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $blobShas = [];

        foreach ($body['tree'] ?? [] as $entry) {
            if (isset($entry['path'], $entry['sha'])) {
                $blobShas[$entry['path']] = $entry['sha'];
            }
        }

        return ['success' => true, 'sha' => $body['sha'] ?? null, 'blob_shas' => $blobShas];
    }

    private function createCommit(string $message, string $treeSha, ?string $parentSha): array
    {
        $payload = [
            'message' => $message,
            'tree' => $treeSha,
            'parents' => $parentSha !== null ? [$parentSha] : [],
        ];

        $url = sprintf('https://api.github.com/repos/%s/git/commits', $this->ownerRepo);
        $response = wp_remote_request($url, [
            'method' => 'POST',
            'headers' => $this->headers(),
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return $this->apiError($response, $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return ['success' => true, 'sha' => $body['sha'] ?? null];
    }

    private function updateRef(string $commitSha, bool $create): array
    {
        if ($create) {
            $url = sprintf('https://api.github.com/repos/%s/git/refs', $this->ownerRepo);
            $method = 'POST';
            $payload = ['ref' => 'refs/heads/' . $this->branch, 'sha' => $commitSha];
        } else {
            $url = sprintf('https://api.github.com/repos/%s/git/refs/heads/%s', $this->ownerRepo, rawurlencode($this->branch));
            $method = 'PATCH';
            $payload = ['sha' => $commitSha];
        }

        $response = wp_remote_request($url, [
            'method' => $method,
            'headers' => $this->headers(),
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return $this->apiError($response, $code);
        }

        return ['success' => true];
    }

    private function apiError($response, int $code): array
    {
        $retryAfter = $this->rateLimitRetryAfter($response, $code);

        if ($retryAfter !== null) {
            return [
                'success' => false,
                'error' => sprintf(__('GitHub rate limit reached. Retrying automatically in %d seconds.', 'post-to-github-md'), $retryAfter),
                'retry_after' => $retryAfter,
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => false,
            'error' => $body['message'] ?? sprintf(__('GitHub API error, HTTP %d.', 'post-to-github-md'), $code),
        ];
    }

    private function rateLimitRetryAfter($response, int $code): ?int
    {
        if ($code !== 403 && $code !== 429) {
            return null;
        }

        $retryAfterHeader = wp_remote_retrieve_header($response, 'retry-after');

        if ($retryAfterHeader !== '' && $retryAfterHeader !== null) {
            return max(1, (int) $retryAfterHeader);
        }

        $remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
        $reset = wp_remote_retrieve_header($response, 'x-ratelimit-reset');

        if ($remaining === '0' && $reset !== '' && $reset !== null) {
            return max(1, (int) $reset - time());
        }

        return null;
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
