<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class BrandingController extends Controller
{
    /**
     * Display the branding settings.
     */
    public function index()
    {
        // Get logo and background from database
        $settings = DB::table('branding_settings')->pluck('value', 'key');
        $logoPath = $settings->get('logo_path');
        $backgroundPath = $settings->get('background_path');

        // Get other branding settings from config file
        $branding = config('branding', []);

        return response()->json([
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'background_url' => $backgroundPath ? Storage::disk('public')->url($backgroundPath) : null,
            'typography' => $branding['typography'] ?? [],
            'colors' => $branding['colors'] ?? [],
            'pages' => $branding['pages'] ?? [],
        ]);
    }

    /**
     * Update the branding settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'background' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',
            'typography' => 'nullable|array',
            'colors' => 'nullable|array',
            'pages' => 'nullable|array',
        ]);

        // Handle file uploads (logo and background)
        if ($request->hasFile('logo')) {
            $this->updateSetting('logo_path', $request->file('logo'));
        }

        if ($request->hasFile('background')) {
            $this->updateSetting('background_path', $request->file('background'));
        }

        // Handle config file updates (colors, fonts, pages)
        if ($request->has('typography') || $request->has('colors') || $request->has('pages')) {
            $this->updateConfigFile($request);
        }

        return response()->json(['message' => 'Branding updated successfully.']);
    }

    /**
     * Helper function to update a specific setting.
     */
    private function updateSetting($key, $file)
    {
        // Get the old path from the database
        $oldPath = DB::table('branding_settings')->where('key', $key)->value('value');

        // Delete the old file if it exists
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Store the new file and get its path
        $path = $file->store('branding', 'public');

        // Update or insert the new path in the database
        DB::table('branding_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $path, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Update config file with new branding settings
     */
    private function updateConfigFile(Request $request)
    {
        $configPath = config_path('branding.php');
        $currentConfig = config('branding', []);

        // Update typography
        if ($request->has('typography')) {
            $currentConfig['typography'] = array_merge(
                $currentConfig['typography'] ?? [],
                $request->typography
            );
        }

        // Update colors
        if ($request->has('colors')) {
            $currentConfig['colors'] = array_merge(
                $currentConfig['colors'] ?? [],
                $request->colors
            );
        }

        // Update page settings
        if ($request->has('pages')) {
            foreach ($request->pages as $page => $settings) {
                $currentConfig['pages'][$page] = array_merge(
                    $currentConfig['pages'][$page] ?? [],
                    $settings
                );
            }
        }

        // Write to file
        $export = var_export($currentConfig, true);
        $content = "<?php\n\nreturn " . $export . ";\n";
        File::put($configPath, $content);

        // Clear config cache
        \Artisan::call('config:clear');
    }

    /**
     * Reset branding to default values
     */
    public function reset()
    {
        // Define default branding configuration
        $defaultConfig = [
            'typography' => [
                'heading_font' => 'System',
                'body_font' => 'System',
            ],
            'colors' => [
                'primary' => '#232326',
                'secondary' => '#202444',
                'tertiary' => '#3949ab',
                'accent' => '#10B981',
                'background' => '#fcfcfc',
                'text_primary' => '#edfcf9',
                'text_secondary' => '#193377',
            ],
            'pages' => [
                'home' => [
                    'background_color' => '#FFFFFF',
                    'hero_background' => '#1a237e',
                    'button_color' => '#1a237e',
                ],
                'news' => [
                    'background_color' => '#FFFFFF',
                    'header_background' => '#1a237e',
                    'card_background' => '#f7f9ff',
                ],
                'collaborate' => [
                    'background_color' => '#F9FAFB',
                    'sidebar_background' => '#FFFFFF',
                    'message_bubble_sent' => '#1a237e',
                ],
                'profile' => [
                    'background_color' => '#FFFFFF',
                    'header_background' => '#1a237e',
                    'card_background' => '#F9FAFB',
                ],
                'forum' => [
                    'background_color' => '#FFFFFF',
                    'header_background' => '#1a237e',
                    'post_background' => '#F9FAFB',
                ],
            ],
        ];

        // Write to config file
        $configPath = config_path('branding.php');
        $export = var_export($defaultConfig, true);
        $content = "<?php\n\nreturn " . $export . ";\n";
        
        // Ensure the file is writable
        if (!File::isWritable($configPath)) {
            return response()->json([
                'message' => 'Config file is not writable.',
                'error' => 'Permission denied'
            ], 500);
        }
        
        File::put($configPath, $content);
        
        // Verify file was written
        if (!File::exists($configPath)) {
            return response()->json([
                'message' => 'Failed to write config file.',
                'error' => 'File not created'
            ], 500);
        }

        // Clear config cache
        \Artisan::call('config:clear');
        \Artisan::call('cache:clear');

        // Optionally, you can also clear logo and background from database
        // DB::table('branding_settings')->truncate();

        return response()->json([
            'message' => 'Branding reset to default successfully.',
            'branding' => $defaultConfig
        ]);
    }
}
