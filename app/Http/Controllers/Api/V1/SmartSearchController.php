<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exceptions\OpenAIUnavailableException;
use App\Http\Requests\SmartSearchRequest;
use App\Models\Book;
use App\Services\OpenAIService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SmartSearchController extends Controller
{
    //
}
