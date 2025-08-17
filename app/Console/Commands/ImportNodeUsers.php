<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Throwable;

/**
 * Import users exported from the legacy Node app.
 * Accepts JSON (array of objects) or CSV with headers.
 */
class ImportNodeUsers extends Command
{
    protected $signature = 'users:import-node 
        {--file= : Path to JSON or CSV export of Node users} 
        {--hash-type=auto : auto|bcrypt|argon2|plaintext (plaintext means provided value is raw password)} 
        {--email-field=email : Field name for email in source} 
        {--name-field=name : Field name for name} 
        {--password-field=password : Field name for password/hash in source} 
        {--limit=0 : Limit number of records imported (0 = all)} 
        {--dry-run : Do not persist, just report}' ;

    protected $description = 'Import existing Node app users (and password hashes) into Laravel users table';

    public function handle(): int
    {
        $path = $this->option('file');
        if (!$path) {
            $this->error('--file is required');
            return Command::FAILURE;
        }
        if (!File::exists($path)) {
            $this->error("File not found: $path");
            return Command::FAILURE;
        }

        $hashType = strtolower($this->option('hash-type') ?? 'auto');
        $emailField = $this->option('email-field');
        $nameField = $this->option('name-field');
        $passwordField = $this->option('password-field');
        $limit = (int)$this->option('limit');
        $dry = (bool)$this->option('dry-run');

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = [];
        try {
            if ($ext === 'json') {
                $decoded = json_decode(File::get($path), true);
                if (!is_array($decoded)) {
                    $this->error('JSON is not an array');
                    return Command::FAILURE;
                }
                $rows = $decoded;
            } elseif (in_array($ext, ['csv','txt'])) {
                $rows = $this->readCsv($path);
            } else {
                $this->error('Unsupported file type. Use JSON or CSV.');
                return Command::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Failed reading file: '.$e->getMessage());
            return Command::FAILURE;
        }

        $count = 0; $created = 0; $skipped = 0; $errors = 0;
        foreach ($rows as $row) {
            $count++;
            if ($limit && $created >= $limit) break;
            $email = $row[$emailField] ?? null;
            $name = $row[$nameField] ?? ($email ? strstr($email,'@', true) : null);
            $pwRaw = $row[$passwordField] ?? null;
            if (!$email || !$pwRaw) { $skipped++; continue; }

            $isHash = $this->isHashLike($pwRaw, $hashType);

            try {
                $user = User::where('email',$email)->first();
                if ($user) { $skipped++; continue; }

                $passwordToStore = $pwRaw;
                if (!$isHash) { $passwordToStore = Hash::make($pwRaw); }

                if (!$dry) {
                    User::create([
                        'name' => $name ?: 'Imported User',
                        'email' => $email,
                        'password' => $passwordToStore,
                    ]);
                }
                $created++;
            } catch (Throwable $e) {
                $errors++;
                $this->warn("Error importing $email: ".$e->getMessage());
                continue;
            }
        }

        $this->info("Processed: $count | Created: $created | Skipped(existing/missing): $skipped | Errors: $errors" . ($dry ? ' (dry-run)':''));
        if ($hashType === 'auto') {
            $this->line('Auto hash detection: bcrypt/argon2 patterns accepted; others treated as plaintext and re-hashed.');
        }
        return Command::SUCCESS;
    }

    protected function isHashLike(string $value, string $hashType): bool
    {
        if ($hashType === 'plaintext') return false;
        if ($hashType === 'bcrypt') return (bool)preg_match('/^\$2[aby]\$/',$value);
        if ($hashType === 'argon2') return str_starts_with($value,'$argon2');
        // auto
        return (bool)(preg_match('/^\$2[aby]\$/',$value) || str_starts_with($value,'$argon2'));
    }

    protected function readCsv(string $path): array
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $headers = [];
        $rows = [];
        foreach ($file as $i => $row) {
            if ($row === [null] || $row === false) continue;
            if ($i === 0) { $headers = $row; continue; }
            $assoc = [];
            foreach ($headers as $idx => $h) {
                if ($h === null || $h === '') continue;
                $assoc[$h] = $row[$idx] ?? null;
            }
            if ($assoc) $rows[] = $assoc;
        }
        return $rows;
    }
}
