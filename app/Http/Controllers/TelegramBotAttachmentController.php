<?php

namespace App\Http\Controllers;

use App\Models\TelegramBotMessage;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TelegramBotAttachmentController extends Controller
{
    public function __invoke(TelegramBotMessage $message, TelegramBotService $bots): Response
    {
        Gate::authorize('view', $message->chat);

        $fileId = $message->telegramFileId();
        if ($fileId === null) {
            abort(404);
        }

        try {
            $fetched = $bots->fetchTelegramFile($fileId);
        } catch (Throwable) {
            abort(404);
        }

        $filename = $message->telegramAttachmentDownloadName() ?? 'telegram-file';
        $safeName = preg_replace('/[^\w\-.]+/u', '_', basename($filename)) ?: 'telegram-file';

        return response($fetched['body'], 200, [
            'Content-Type' => $fetched['content_type'],
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
