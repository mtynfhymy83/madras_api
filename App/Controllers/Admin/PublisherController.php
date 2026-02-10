<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\PublisherService;
use App\DTOs\Admin\Publisher\CreatePublisherRequestDTO;
use Exception;

class PublisherController extends Controller
{
    private PublisherService $publisherService;

    public function __construct()
    {
        parent::__construct();
        $this->publisherService = new PublisherService();
    }

    /**
     * Get list of publishers
     */
    public function index($request)
    {
        $queryParams = [];

        if (is_array($request)) {
            $queryParams = $request;
        } elseif (is_object($request) && isset($request->get) && is_array($request->get)) {
            $queryParams = $request->get;
        }

        try {
            $result = $this->publisherService->getPublishersList($queryParams);
            return $this->sendResponse($result, 'لیست ناشران با موفقیت دریافت شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, 'خطا در دریافت لیست ناشران: ' . $e->getMessage(), true, 500);
        }
    }

    /**
     * Create a new publisher
     */
    public function store($request)
    {
        // Convert request to array
        $data = [];
        if (is_array($request)) {
            $data = $request;
        } elseif (is_object($request)) {
            if (method_exists($request, 'getPostData')) {
                $data = $request->getPostData();
            } elseif (isset($request->post)) {
                $data = $request->post;
            } else {
                $data = (array)$request;
            }
        }

        // Validate required fields
        try {
            $this->validate([
                'title' => 'required|string|min:2|max:255',
                'slug' => 'string|max:255',
                'logo' => 'string|max:500',
                'description' => 'string',
                'website' => 'string|max:500',
            ], $data);
        } catch (Exception $e) {
            return $this->sendResponse(null, $e->getMessage(), true, 400);
        }

        try {
            // Create DTO
            $dto = CreatePublisherRequestDTO::fromArray($data);

            // Create publisher
            $publisher = $this->publisherService->createPublisher($dto);

            return $this->sendResponse($publisher, 'ناشر با موفقیت ایجاد شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, 'خطا در ایجاد ناشر: ' . $e->getMessage(), true, 500);
        }
    }

    /**
     * Get publisher by ID
     */
    public function show($request, $id)
    {
        try {
            $publisherId = (int)$id;
            $publisher = $this->publisherService->getPublisherById($publisherId);

            if (!$publisher) {
                return $this->sendResponse(null, 'ناشر یافت نشد', true, 404);
            }

            return $this->sendResponse($publisher, 'ناشر با موفقیت دریافت شد');
        } catch (Exception $e) {
            return $this->sendResponse(null, 'خطا در دریافت ناشر: ' . $e->getMessage(), true, 500);
        }
    }
}
