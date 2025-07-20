<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /**
     * Display the branding settings.
     */
    public function index()
    {
        $settings = DB::table('branding_settings')->pluck('value', 'key');

        $logoPath = $settings->get('logo_path');
        $backgroundPath = $settings->get('background_path');

        return response()->json([
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'background_url' => $backgroundPath ? Storage::disk('public')->url($backgroundPath) : null,
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
        ]);

        if ($request->hasFile('logo')) {
            $this->updateSetting('logo_path', $request->file('logo'));
        }

        if ($request->hasFile('background')) {
            $this->updateSetting('background_path', $request->file('background'));
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
}
