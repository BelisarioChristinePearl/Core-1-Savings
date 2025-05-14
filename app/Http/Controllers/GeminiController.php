<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GeminiService;

class GeminiController extends Controller
{
    public function chatbot(){
        return view('user.chatbot');
    }
    protected $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000'
        ]);

        $prompt = $request->input('prompt');
        $response = $this->geminiService->generateResponse($prompt);

        return response()->json(['response' => $response]);
    }
}
