<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AppLogController extends Controller
{
    public function store(Request $request)
    {
        $input = $request->all();
        $rawBody = $request->getContent();

        if (!array_key_exists('logs', $input) && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $input = array_merge($decoded, $input);
            } else {
                $input['logs'] = $rawBody;
            }
        }

        $logs = (string) ($input['logs'] ?? '');
        if (trim($logs) === '') {
            return response()->json([
                'success' => false,
                'message' => 'No logs provided',
                'debug' => [
                    'contentType' => $request->headers->get('content-type'),
                    'contentLength' => $request->headers->get('content-length'),
                    'rawLength' => strlen($rawBody),
                    'inputKeys' => array_keys($input),
                ],
            ], 400);
        }

        $directory = base_path('flutter_logs');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $source = (string) ($input['source'] ?? 'flutter-app');
        $source = preg_replace('/[^A-Za-z0-9._-]/', '-', $source) ?: 'flutter-app';
        $filename = sprintf(
            'app_logs_%s_%s.txt',
            $source,
            now()->format('Y-m-d\\TH-i-s')
        );

        $payload = implode(PHP_EOL, [
            'TIMESTAMP: ' . now()->toIso8601String(),
            'DEVICE INFO: ' . (string) ($input['deviceInfo'] ?? 'Unknown device'),
            'SOURCE: ' . $source,
            'CLIENT TIMESTAMP: ' . (string) ($input['timestamp'] ?? ''),
            '',
            $logs,
        ]);

        File::put($directory . DIRECTORY_SEPARATOR . $filename, $payload);

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'fileSaved' => true,
        ]);
    }
}
