<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AppLogController extends Controller
{
    public function store(Request $request)
    {
        $logs = (string) $request->input('logs', '');
        if (trim($logs) === '') {
            return response()->json([
                'success' => false,
                'message' => 'No logs provided',
            ], 400);
        }

        $directory = base_path('flutter_logs');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $source = (string) $request->input('source', 'flutter-app');
        $source = preg_replace('/[^A-Za-z0-9._-]/', '-', $source) ?: 'flutter-app';
        $filename = sprintf(
            'app_logs_%s_%s.txt',
            $source,
            now()->format('Y-m-d\\TH-i-s')
        );

        $payload = implode(PHP_EOL, [
            'TIMESTAMP: ' . now()->toIso8601String(),
            'DEVICE INFO: ' . (string) $request->input('deviceInfo', 'Unknown device'),
            'SOURCE: ' . $source,
            'CLIENT TIMESTAMP: ' . (string) $request->input('timestamp', ''),
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