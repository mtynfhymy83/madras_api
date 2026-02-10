<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\BookService;
use App\Services\UploadService;
use App\DTOs\Admin\Book\CreateBookRequestDTO;
use Exception;

class BookController extends Controller
{
    private BookService $bookService;
    private UploadService $uploadService;

    public function __construct()
    {
        parent::__construct();
        $this->bookService = new BookService();
        $this->uploadService = new UploadService();
    }

    public function index($request)
    {
        $queryParams = [];

        if (is_array($request)) {
            $queryParams = $request;
        } elseif (is_object($request) && isset($request->get) && is_array($request->get)) {
            $queryParams = $request->get;
        }

        $result = $this->bookService->getBooksList($queryParams);

        return $this->sendResponse($result, 'لیست کتاب‌ها با موفقیت دریافت شد');
    }

    public function store($request)
    {
        // Convert request to array
        $data = [];
        if (is_array($request)) {
            $data = $request;
        } elseif (is_object($request)) {
            $data = (array)$request;
        }

        // Validate required fields
        $this->validate([
            'title' => 'required|string|min:3|max:500',
            'priority' => 'enum:normal,high,عادی,بالا',
            'status' => 'enum:publish,draft,ready,انتشار,پیش_نویس,آماده_انتشار',
            'price' => 'integer|min:0',
            'category_ids' => 'array',
        ], $data);

        try {
            // Create DTO
            $dto = CreateBookRequestDTO::fromArray($data);

            // Handle file uploads with new system
            try {
                // Get current user info (you should implement proper auth)
                // For now, assuming admin user
                $userFolder = 'admin'; // or get from session/JWT
                
                // Upload vertical image (cover)
                if (!empty($data['vertical_image']) && is_string($data['vertical_image'])) {
                    // Debug: Log base64 info (first 100 chars)
                    error_log("Base64 Debug - Length: " . strlen($data['vertical_image']) . ", First 100 chars: " . substr($data['vertical_image'], 0, 100));
                    
                    $uploadResult = $this->uploadService->uploadFromBase64($data['vertical_image'], [
                        'type' => 'image',
                        'user_folder' => $userFolder,
                        'filename' => $data['title'] . '-vertical.jpg',
                        'disk' => $_ENV['STORAGE_DRIVER'] ?? 'local',
                        'optimize' => true,
                        'sizes' => [
                            'thumb' => [
                                'width' => 150,
                                'height' => 200,
                                'webp' => true,
                                'options' => ['quality' => 80],
                            ],
                            'medium' => [
                                'width' => 300,
                                'height' => 400,
                                'webp' => true,
                                'options' => ['quality' => 85],
                            ],
                            'large' => [
                                'width' => 600,
                                'height' => 800,
                                'webp' => true,
                                'options' => ['quality' => 90],
                            ],
                        ],
                        'validation' => [
                            'max_size' => 5 * 1024 * 1024, // 5MB
                        ],
                    ]);
                    
                    $dto->verticalImage = json_encode($uploadResult);
                }

                // Upload horizontal image (banner)
                if (!empty($data['horizontal_image']) && is_string($data['horizontal_image'])) {
                    // Debug: Log base64 info
                    error_log("Base64 Debug (horizontal) - Length: " . strlen($data['horizontal_image']) . ", First 100 chars: " . substr($data['horizontal_image'], 0, 100));
                    
                    $uploadResult = $this->uploadService->uploadFromBase64($data['horizontal_image'], [
                        'type' => 'image',
                        'user_folder' => $userFolder,
                        'filename' => $data['title'] . '-horizontal.jpg',
                        'disk' => $_ENV['STORAGE_DRIVER'] ?? 'local',
                        'optimize' => true,
                        'sizes' => [
                            'thumb' => [
                                'width' => 300,
                                'height' => 150,
                                'webp' => true,
                            ],
                            'medium' => [
                                'width' => 800,
                                'height' => 400,
                                'webp' => true,
                            ],
                            'large' => [
                                'width' => 1600,
                                'height' => 800,
                                'webp' => true,
                            ],
                        ],
                        'validation' => [
                            'max_size' => 5 * 1024 * 1024,
                        ],
                    ]);
                    
                    $dto->horizontalImage = json_encode($uploadResult);
                }

                // Upload sample questions file (PDF)
                if (!empty($data['sample_questions_file']) && is_string($data['sample_questions_file'])) {
                    $uploadResult = $this->uploadService->uploadFromBase64($data['sample_questions_file'], [
                        'type' => 'document',
                        'user_folder' => $userFolder,
                        'filename' => $data['title'] . '-sample.pdf',
                        'disk' => $_ENV['STORAGE_DRIVER'] ?? 'local',
                        'validation' => [
                            'max_size' => 50 * 1024 * 1024, // 50MB
                        ],
                    ]);
                    
                    $dto->sampleQuestionsFile = $uploadResult['original']['url'];
                }
            } catch (\Exception $uploadException) {
                return $this->sendResponse(null, 'خطا در آپلود فایل: ' . $uploadException->getMessage(), true, 400);
            }

            // Create book
            $book = $this->bookService->createBook($dto);

            return $this->sendResponse($book, 'کتاب با موفقیت ایجاد شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, 500);
        }
    }
}
