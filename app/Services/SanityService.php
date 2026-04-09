<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SanityContent;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sanity\Client as SanityClient;
use Sanity\Exception\BaseException as SanityException;

final class SanityService
{
    private ?SanityClient $client = null;

    private readonly string $projectId;

    private readonly string $dataset;

    private readonly string $token;

    private readonly string $apiVersion;

    public function __construct()
    {
        $this->projectId = config('services.sanity.project_id') ?? '';
        $this->dataset = config('services.sanity.dataset') ?? 'production';
        $this->token = config('services.sanity.token') ?? '';
        $this->apiVersion = config('services.sanity.api_version', '2023-05-03');

        $this->initializeClient();
    }

    /**
     * Check if Sanity is configured
     */
    public function isConfigured(): bool
    {
        return $this->client instanceof SanityClient;
    }

    /**
     * Get the Sanity client instance
     */
    public function getClient(): ?SanityClient
    {
        return $this->client;
    }

    /**
     * Get project ID for frontend usage
     */
    public function getProjectId(): string
    {
        return $this->projectId;
    }

    /**
     * Get dataset for frontend usage
     */
    public function getDataset(): string
    {
        return $this->dataset;
    }

    /**
     * Fetch all documents from Sanity using GROQ query
     */
    public function getAllDocuments(?string $type = null): Collection
    {
        if (! $this->isConfigured()) {
            Log::warning('Sanity is not configured. Please set SANITY_PROJECT_ID and SANITY_API_TOKEN in your .env file.');

            return collect([]);
        }

        try {
            $query = $type ? '*[_type == $type]' : '*';
            $params = $type ? ['type' => $type] : [];

            Log::info('Fetching from Sanity', [
                'query' => $query,
                'params' => $params,
                'project_id' => $this->projectId,
                'dataset' => $this->dataset,
            ]);

            $results = $this->client->fetch($query, $params);

            Log::info('Sanity API Response', [
                'result_count' => is_array($results) ? count($results) : 0,
            ]);

            return collect($results ?? []);
        } catch (SanityException $e) {
            Log::error('Sanity API Exception', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return collect([]);
        }
    }

    /**
     * Fetch a single document by ID
     */
    public function getDocument(string $documentId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            return $this->client->getDocument($documentId);
        } catch (SanityException $e) {
            Log::error('Sanity API Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Perform a GROQ query
     */
    public function query(string $groqQuery, array $params = []): mixed
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            return $this->client->fetch($groqQuery, $params);
        } catch (SanityException $e) {
            Log::error('Sanity Query Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create a new document in Sanity
     */
    public function createDocument(array $documentData): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Sanity is not configured. Skipping document creation.');

            return null;
        }

        try {
            $result = $this->client->create($documentData);

            Log::info('Sanity document created', ['id' => $result['_id'] ?? null]);

            return $result;
        } catch (SanityException $e) {
            Log::error('Sanity Create Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create a document if it does not exist
     */
    public function createIfNotExists(array $documentData): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            return $this->client->createIfNotExists($documentData);
        } catch (SanityException $e) {
            Log::error('Sanity CreateIfNotExists Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create or replace a document (upsert)
     */
    public function createOrReplace(array $documentData): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            return $this->client->createOrReplace($documentData);
        } catch (SanityException $e) {
            Log::error('Sanity CreateOrReplace Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Update an existing document in Sanity using patch
     */
    public function updateDocument(string $documentId, array $updates): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $result = $this->client
                ->patch($documentId)
                ->set($updates)
                ->commit();

            Log::info('Sanity document updated', ['id' => $documentId]);

            return $result;
        } catch (SanityException $e) {
            Log::error('Sanity Update Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Patch a document with multiple operations
     */
    public function patchDocument(string $documentId, callable $patchCallback): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $patch = $this->client->patch($documentId);
            $patchCallback($patch);

            return $patch->commit();
        } catch (SanityException $e) {
            Log::error('Sanity Patch Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Delete a document from Sanity
     */
    public function deleteDocument(string $documentId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $this->client->delete($documentId);
            Log::info('Sanity document deleted', ['id' => $documentId]);

            return true;
        } catch (SanityException $e) {
            Log::error('Sanity Delete Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Execute multiple mutations in a transaction
     */
    public function transaction(callable $transactionCallback): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $transaction = $this->client->transaction();
            $transactionCallback($transaction);

            return $transaction->commit();
        } catch (SanityException $e) {
            Log::error('Sanity Transaction Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upload an image from a file path
     */
    public function uploadImageFromFile(string $filePath, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Sanity is not configured. Skipping image upload.');

            return null;
        }

        try {
            $asset = $this->client->uploadAssetFromFile('image', $filePath, $options);

            return $this->formatAssetResponse($asset, $filePath);
        } catch (SanityException $e) {
            Log::error('Sanity Image Upload Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upload an image from uploaded file (Laravel UploadedFile)
     */
    public function uploadImage($file): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Sanity is not configured. Skipping image upload.');

            return null;
        }

        try {
            $filePath = $file->getRealPath();
            $filename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();

            // Validate file exists and is readable
            if (! $filePath || ! file_exists($filePath) || ! is_readable($filePath)) {
                Log::error('Sanity Image Upload: File not accessible', [
                    'path' => $filePath,
                    'exists' => file_exists($filePath),
                ]);

                return null;
            }

            // Read file contents
            $fileContents = file_get_contents($filePath);
            if ($fileContents === false) {
                Log::error('Sanity Image Upload: Could not read file contents');

                return null;
            }

            Log::info('Uploading image to Sanity', [
                'filename' => $filename,
                'mimeType' => $mimeType,
                'size' => mb_strlen($fileContents),
            ]);

            // Use uploadAssetFromString for more control
            $asset = $this->client->uploadAssetFromString(
                'image',
                $fileContents,
                [
                    'filename' => $filename,
                    'contentType' => $mimeType,
                ]
            );

            Log::info('Sanity image uploaded successfully', ['asset_id' => $asset['_id'] ?? null]);

            return $this->formatAssetResponse($asset, $filename);
        } catch (SanityException $e) {
            Log::error('Sanity Image Upload Error: '.$e->getMessage());

            return null;
        } catch (Exception $e) {
            Log::error('Sanity Image Upload Exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upload an image from string/buffer
     */
    public function uploadImageFromString(string $imageData, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $asset = $this->client->uploadAssetFromString('image', $imageData, $options);

            return $this->formatAssetResponse($asset, $options['filename'] ?? 'uploaded-image');
        } catch (SanityException $e) {
            Log::error('Sanity Image Upload Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Upload a file asset
     */
    public function uploadFile($file, array $options = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $mergedOptions = array_merge([
                'filename' => $file->getClientOriginalName(),
                'contentType' => $file->getMimeType(),
            ], $options);

            $asset = $this->client->uploadAssetFromFile('file', $file->getRealPath(), $mergedOptions);

            return $this->formatAssetResponse($asset, $file->getClientOriginalName());
        } catch (SanityException $e) {
            Log::error('Sanity File Upload Error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create an image reference for use in documents
     */
    public function createImageReference(string $assetId): array
    {
        return [
            '_type' => 'image',
            'asset' => [
                '_type' => 'reference',
                '_ref' => $assetId,
            ],
        ];
    }

    /**
     * Create a file reference for use in documents
     */
    public function createFileReference(string $assetId): array
    {
        return [
            '_type' => 'file',
            'asset' => [
                '_type' => 'reference',
                '_ref' => $assetId,
            ],
        ];
    }

    /**
     * Sync Sanity content to local database
     */
    public function syncToDatabase(?string $type = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'synced' => 0,
                'created' => 0,
                'updated' => 0,
                'message' => 'Sanity is not configured. Please add SANITY_PROJECT_ID and SANITY_API_TOKEN to your .env file.',
            ];
        }

        $documents = $this->getAllDocuments($type);
        $syncedCount = 0;
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($documents as $doc) {
            try {
                $data = $this->mapSanityDocumentToDatabase($doc);
                $existing = SanityContent::where('sanity_id', $doc['_id'])->first();

                SanityContent::updateOrCreate(
                    ['sanity_id' => $doc['_id']],
                    $data
                );

                if ($existing) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
                $syncedCount++;
            } catch (Exception $e) {
                Log::error("Error syncing Sanity document {$doc['_id']}: ".$e->getMessage());
            }
        }

        Cache::forget('sanity_content_count');

        return [
            'success' => true,
            'synced' => $syncedCount,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'message' => "Synced {$syncedCount} posts ({$createdCount} new, {$updatedCount} updated)",
        ];
    }

    /**
     * Sync a single post to Sanity (create or update)
     */
    public function syncPostToSanity(SanityContent $content): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $documentData = $this->mapDatabaseToSanityDocument($content);

        Log::info('Syncing post to Sanity', [
            'id' => $content->id,
            'sanity_id' => $content->sanity_id,
            'featured_image_payload' => $documentData['featuredImage'] ?? 'null',
            'raw_featured_image' => $content->featured_image,
        ]);

        try {
            if ($content->sanity_id && ! str_starts_with($content->sanity_id, 'draft_')) {
                // Update existing document
                $result = $this->updateDocument($content->sanity_id, $documentData);

                return $result;
            }

            // Create new document
            $documentData['_type'] = 'post';
            $result = $this->createDocument($documentData);

            if ($result && isset($result['_id'])) {
                $content->update(['sanity_id' => $result['_id']]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to sync post to Sanity: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Bulk sync multiple posts to Sanity using transactions
     */
    public function bulkSyncToSanity(Collection $contents): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'synced' => 0,
                'message' => 'Sanity is not configured.',
            ];
        }

        $synced = 0;

        try {
            $result = $this->transaction(function ($transaction) use ($contents, &$synced): void {
                foreach ($contents as $content) {
                    $documentData = $this->mapDatabaseToSanityDocument($content);

                    if ($content->sanity_id && ! str_starts_with((string) $content->sanity_id, 'draft_')) {
                        // Create a patch for existing documents
                        $patch = $this->client->patch($content->sanity_id)->set($documentData);
                        $transaction->patch($patch);
                    } else {
                        $documentData['_type'] = 'post';
                        $transaction->create($documentData);
                    }
                    $synced++;
                }
            });

            return [
                'success' => true,
                'synced' => $synced,
                'message' => "Successfully synced {$synced} posts to Sanity.",
            ];
        } catch (Exception $e) {
            Log::error('Bulk sync to Sanity failed: '.$e->getMessage());

            return [
                'success' => false,
                'synced' => $synced,
                'message' => 'Bulk sync failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get or create the System Administrator author profile
     */
    public function getOrCreateSystemAuthor(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            // Check if System Admin exists
            $query = "*[_type == 'person' && name == 'System Administrator'][0]._id";
            $existingId = $this->client->fetch($query);

            if ($existingId) {
                return $existingId;
            }

            // Create System Admin
            $doc = [
                '_type' => 'person',
                'name' => 'System Administrator',
                'slug' => [
                    '_type' => 'slug',
                    'current' => 'system-administrator',
                ],
                'bio' => [
                    [
                        '_type' => 'block',
                        'children' => [
                            [
                                '_type' => 'span',
                                'text' => 'Default system administrator profile.',
                            ],
                        ],
                        'markDefs' => [],
                        'style' => 'normal',
                    ],
                ],
            ];

            $result = $this->client->create($doc);

            return $result['_id'] ?? null;
        } catch (Exception $e) {
            Log::error('Failed to get/create System Author: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Initialize the Sanity PHP client
     */
    private function initializeClient(): void
    {
        if ($this->projectId && $this->token) {
            $this->client = new SanityClient([
                'projectId' => $this->projectId,
                'dataset' => $this->dataset,
                'token' => $this->token,
                'apiVersion' => $this->apiVersion,
                // Cannot use CDN with token (authenticated requests)
                'useCdn' => false,
            ]);
        }
    }

    /**
     * Format asset response for consistent output
     */
    private function formatAssetResponse(array $asset, string $filename): array
    {
        $assetId = $asset['_id'] ?? null;
        $url = $asset['url'] ?? null;

        // Generate CDN URL if not provided
        if (! $url && $assetId) {
            $url = $this->buildAssetUrl($assetId);
        }

        // Generate alt text from filename (remove extension and replace special chars)
        $altText = $this->generateAltTextFromFilename($filename);

        return [
            'assetId' => $assetId,
            'url' => $url,
            'filename' => $filename,
            'alt' => $altText,
            'mimeType' => $asset['mimeType'] ?? null,
            'size' => $asset['size'] ?? null,
            'metadata' => $asset['metadata'] ?? null,
        ];
    }

    /**
     * Generate alt text from filename
     */
    private function generateAltTextFromFilename(string $filename): string
    {
        // Remove file extension
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Replace underscores, hyphens, and multiple spaces with single space
        $name = preg_replace('/[-_]+/', ' ', $name);

        // Remove any non-alphanumeric characters except spaces
        $name = preg_replace('/[^a-zA-Z0-9\s]/', '', (string) $name);

        // Trim and capitalize first letter of each word
        $name = ucwords(mb_strtolower(mb_trim($name)));

        // Fallback if empty
        if ($name === '' || $name === '0') {
            return 'Uploaded image';
        }

        return $name;
    }

    /**
     * Build CDN URL from asset ID
     */
    private function buildAssetUrl(string $assetId): ?string
    {
        // Asset ID format: image-<hash>-<width>x<height>-<extension>
        // URL format: https://cdn.sanity.io/images/<projectId>/<dataset>/<hash>-<width>x<height>.<extension>

        $type = str_starts_with($assetId, 'image-') ? 'images' : 'files';
        $prefix = str_starts_with($assetId, 'image-') ? 'image-' : 'file-';

        $filename = str_replace($prefix, '', $assetId);
        $lastDash = mb_strrpos($filename, '-');

        if ($lastDash !== false) {
            $filename = substr_replace($filename, '.', $lastDash, 1);

            return "https://cdn.sanity.io/{$type}/{$this->projectId}/{$this->dataset}/{$filename}";
        }

        return null;
    }

    /**
     * Map Sanity document fields to database fields
     */
    private function mapSanityDocumentToDatabase(array $doc): array
    {
        return [
            'sanity_id' => $doc['_id'],
            'post_kind' => $doc['postKind'] ?? 'news',
            'title' => $doc['title'] ?? 'Untitled',
            'slug' => $doc['slug']['current'] ?? $doc['slug'] ?? null,
            'excerpt' => $doc['excerpt'] ?? null,
            'content' => $doc['content'] ?? null,
            'content_focus' => $doc['contentFocus'] ?? null,
            'featured_image' => $doc['featuredImage'] ?? null,
            'priority' => $doc['priority'] ?? 'normal',
            'activation_window' => $doc['activationWindow'] ?? null,
            'channels' => $doc['channels'] ?? null,
            'cta' => $doc['cta'] ?? null,
            'status' => $doc['status'] ?? 'draft',
            'published_at' => isset($doc['publishedAt']) ? new DateTimeImmutable($doc['publishedAt']) : null,
            'sanity_updated_at' => isset($doc['_updatedAt']) ? new DateTimeImmutable($doc['_updatedAt']) : null,
            'featured' => $doc['featured'] ?? false,
            'category' => $doc['category'] ?? null,
            'author' => $doc['author'] ?? null,
            'tags' => $doc['tags'] ?? null,
            'audiences' => $doc['audiences'] ?? null,
            'primary_category_id' => $doc['primaryCategory']['_ref'] ?? null,
            'department_ids' => array_map(fn (array $ref) => $ref['_ref'], $doc['departments'] ?? []),
            'program_ids' => array_map(fn (array $ref) => $ref['_ref'], $doc['programs'] ?? []),
            'author_ids' => array_map(fn (array $ref) => $ref['_ref'], $doc['authors'] ?? []),
            'related_post_ids' => array_map(fn (array $ref) => $ref['_ref'], $doc['relatedPosts'] ?? []),
            'seo' => $doc['seo'] ?? null,
            'meta_data' => $doc,
        ];
    }

    /**
     * Map database content to Sanity document format
     */
    private function mapDatabaseToSanityDocument(SanityContent $content): array
    {
        $documentData = [
            'title' => $content->title,
            'slug' => ['_type' => 'slug', 'current' => $content->slug],
            'excerpt' => $content->excerpt,
            'postKind' => $content->post_kind,
            'content' => $content->content,
            'status' => $content->status,
            'publishedAt' => format_timestamp($content->published_at) ?? format_timestamp_now(),
            'priority' => $content->priority ?? 'normal',
            'featured' => $content->featured ?? false,
        ];

        if ($content->content_focus) {
            $documentData['contentFocus'] = $content->content_focus;
        }
        if ($content->featured_image) {
            // Convert the stored image data to Sanity's image reference format
            $featuredImage = $content->featured_image;
            if (! empty($featuredImage['assetId'])) {
                $imageObject = [
                    'asset' => [
                        '_type' => 'reference',
                        '_ref' => mb_trim($featuredImage['assetId']),
                    ],
                ];

                // Only add other fields if they have values
                if (! empty($featuredImage['alt'])) {
                    $imageObject['alt'] = $featuredImage['alt'];
                } elseif (! empty($featuredImage['filename'])) {
                    // Fallback to filename if alt is missing
                    $imageObject['alt'] = $this->generateAltTextFromFilename($featuredImage['filename']);
                } else {
                    // Final fallback
                    $imageObject['alt'] = 'Featured Image';
                }

                if (! empty($featuredImage['caption'])) {
                    $imageObject['caption'] = $featuredImage['caption'];
                }
                if (! empty($featuredImage['credit'])) {
                    $imageObject['credit'] = $featuredImage['credit'];
                }

                $documentData['featuredImage'] = $imageObject;
            } else {
                // Explicitly set to null if assetId is missing but field exists
                $documentData['featuredImage'] = null;
            }
        } else {
            // Explicitly set to null if no featured image data exists
            $documentData['featuredImage'] = null;
        }

        // Handle relationships
        if ($content->author_ids) {
            $documentData['authors'] = array_map(fn ($id): array => [
                '_type' => 'reference',
                '_ref' => $id,
                '_key' => md5((string) $id), // Sanity arrays need keys
            ], $content->author_ids);
        } elseif ($content->isNewsOrStory()) {
            // News/Story requires an author. If none, assign System Admin.
            $systemAuthorId = $this->getOrCreateSystemAuthor();
            if ($systemAuthorId) {
                $documentData['authors'] = [
                    [
                        '_type' => 'reference',
                        '_ref' => $systemAuthorId,
                        '_key' => md5($systemAuthorId),
                    ],
                ];
                // Also update the local model so it reflects the state
                $content->update(['author_ids' => [$systemAuthorId]]);
            }
        }

        if ($content->department_ids) {
            $documentData['departments'] = array_map(fn ($id): array => ['_type' => 'reference', '_ref' => $id, '_key' => md5((string) $id)], $content->department_ids);
        }

        if ($content->program_ids) {
            $documentData['programs'] = array_map(fn ($id): array => ['_type' => 'reference', '_ref' => $id, '_key' => md5((string) $id)], $content->program_ids);
        }

        if ($content->related_post_ids) {
            $documentData['relatedPosts'] = array_map(fn ($id): array => ['_type' => 'reference', '_ref' => $id, '_key' => md5((string) $id)], $content->related_post_ids);
        }

        if ($content->tags) {
            $documentData['tags'] = $content->tags;
        }
        if ($content->audiences) {
            $documentData['audiences'] = $content->audiences;
        }
        if ($content->channels) {
            $documentData['channels'] = $content->channels;
        }
        if ($content->cta) {
            $documentData['cta'] = $content->cta;
        }
        if ($content->activation_window) {
            $documentData['activationWindow'] = $content->activation_window;
        }

        return $documentData;
    }
}
