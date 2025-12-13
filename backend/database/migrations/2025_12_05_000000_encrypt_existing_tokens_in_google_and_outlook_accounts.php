<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration {
    /**
     * Check if a value is already encrypted by Laravel's encryption.
     * Laravel encrypted values are base64-encoded JSON with specific structure.
     */
    private function isEncrypted(string $value): bool
    {
        try {
            // Try to decrypt - if it succeeds, it's already encrypted
            Crypt::decryptString($value);
            return true;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Decryption failed, so it's not encrypted
            return false;
        } catch (\Exception $e) {
            // Other exceptions (like invalid base64) mean it's not encrypted
            return false;
        }
    }

    /**
     * Run the migrations.
     * 
     * This migration encrypts existing tokens and refresh tokens in google_accounts
     * and outlook_accounts tables. It checks if tokens are already encrypted by
     * attempting to decrypt them. If decryption fails, the token is encrypted.
     */
    public function up(): void
    {
        // Encrypt Google accounts tokens
        $googleAccounts = DB::table('google_accounts')->get();
        
        foreach ($googleAccounts as $account) {
            $updates = [];
            
            // Encrypt token if not already encrypted
            if (!empty($account->token) && !$this->isEncrypted($account->token)) {
                $updates['token'] = Crypt::encryptString($account->token);
            }
            
            // Encrypt refresh_token if not already encrypted
            if (!empty($account->refresh_token) && !$this->isEncrypted($account->refresh_token)) {
                $updates['refresh_token'] = Crypt::encryptString($account->refresh_token);
            }
            
            // Update only if there are changes
            if (!empty($updates)) {
                DB::table('google_accounts')
                    ->where('id', $account->id)
                    ->update($updates);
            }
        }
        
        // Encrypt Outlook accounts tokens
        $outlookAccounts = DB::table('outlook_accounts')->get();
        
        foreach ($outlookAccounts as $account) {
            $updates = [];
            
            // Encrypt token if not already encrypted
            if (!empty($account->token) && !$this->isEncrypted($account->token)) {
                $updates['token'] = Crypt::encryptString($account->token);
            }
            
            // Encrypt refresh_token if not already encrypted
            if (!empty($account->refresh_token) && !$this->isEncrypted($account->refresh_token)) {
                $updates['refresh_token'] = Crypt::encryptString($account->refresh_token);
            }
            
            // Update only if there are changes
            if (!empty($updates)) {
                DB::table('outlook_accounts')
                    ->where('id', $account->id)
                    ->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     * 
     * WARNING: This will decrypt all tokens. Only use this if you need to rollback
     * and understand the security implications.
     */
    public function down(): void
    {
        // Decrypt Google accounts tokens
        $googleAccounts = DB::table('google_accounts')->get();
        
        foreach ($googleAccounts as $account) {
            $updates = [];
            
            // Decrypt token if encrypted
            if (!empty($account->token)) {
                try {
                    $decrypted = Crypt::decryptString($account->token);
                    $updates['token'] = $decrypted;
                } catch (\Exception $e) {
                    // Already decrypted or invalid, skip
                }
            }
            
            // Decrypt refresh_token if encrypted
            if (!empty($account->refresh_token)) {
                try {
                    $decrypted = Crypt::decryptString($account->refresh_token);
                    $updates['refresh_token'] = $decrypted;
                } catch (\Exception $e) {
                    // Already decrypted or invalid, skip
                }
            }
            
            // Update only if there are changes
            if (!empty($updates)) {
                DB::table('google_accounts')
                    ->where('id', $account->id)
                    ->update($updates);
            }
        }
        
        // Decrypt Outlook accounts tokens
        $outlookAccounts = DB::table('outlook_accounts')->get();
        
        foreach ($outlookAccounts as $account) {
            $updates = [];
            
            // Decrypt token if encrypted
            if (!empty($account->token)) {
                try {
                    $decrypted = Crypt::decryptString($account->token);
                    $updates['token'] = $decrypted;
                } catch (\Exception $e) {
                    // Already decrypted or invalid, skip
                }
            }
            
            // Decrypt refresh_token if encrypted
            if (!empty($account->refresh_token)) {
                try {
                    $decrypted = Crypt::decryptString($account->refresh_token);
                    $updates['refresh_token'] = $decrypted;
                } catch (\Exception $e) {
                    // Already decrypted or invalid, skip
                }
            }
            
            // Update only if there are changes
            if (!empty($updates)) {
                DB::table('outlook_accounts')
                    ->where('id', $account->id)
                    ->update($updates);
            }
        }
    }
};

