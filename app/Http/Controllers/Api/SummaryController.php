<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Summary;
use App\Services\AiService;
use App\Services\PdfService;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    protected $aiService;
    protected $pdfService;

    public function __construct(AiService $aiService, PdfService $pdfService)
    {
        $this->aiService = $aiService;
        $this->pdfService = $pdfService;
    }

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->summaries()->latest()->get()
        );
    }

    public function generate(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        try {
            $results = $this->aiService->generateSummary($request->topic);

            $summary = Summary::create([
                'user_id' => $request->user()->id,
                'topic' => $request->topic,
                'content' => json_encode($results),
            ]);

            return response()->json([
                'message' => 'Summary generated successfully',
                'summary' => [
                    'id' => $summary->id,
                    'topic' => $summary->topic,
                    'content' => $results, // Return the array directly
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|max:255',
            'content' => 'required',
        ]);

        $summary = Summary::create([
            'user_id' => $request->user()->id,
            'topic' => $request->topic,
            'content' => is_array($request->content) ? json_encode($request->content) : $request->content,
        ]);

        return response()->json([
            'message' => 'Summary stored successfully',
            'summary' => $summary,
        ]);
    }

    public function destroy(Summary $summary)
    {
        if (auth()->id() !== $summary->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $summary->delete();

        return response()->json([
            'message' => 'Summary deleted successfully',
        ]);
    }
    public function exportPdf(Summary $summary)
    {
        // Simple auth check
        if (auth()->id() !== $summary->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $contentData = json_decode($summary->content, true);
        $htmlContent = "";
        
        if (is_array($contentData)) {
            foreach ($contentData as $provider => $text) {
                $htmlContent .= "<h2>" . ucfirst($provider) . "</h2>";
                $htmlContent .= "<p>" . nl2br(e($text)) . "</p><hr>";
            }
        } else {
            $htmlContent = nl2br(e($summary->content));
        }

        $pdfContent = $this->pdfService->generateFromHtml($summary->topic, $htmlContent);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . str($summary->topic)->slug() . '.pdf"');
    }
}


