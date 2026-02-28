<?php

namespace App\Services;

use App\Models\Display;
use App\Helpers\DisplaySettings;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * Available default background images
     */
    public const DEFAULT_BACKGROUNDS = [
        'default_1' => 'images/backgrounds/default_1.jpg',
        'default_2' => 'images/backgrounds/default_2.jpg',
        'default_3' => 'images/backgrounds/default_3.jpg',
        'default_4' => 'images/backgrounds/default_4.jpg',
        'default_5' => 'images/backgrounds/default_5.jpg',
        'default_6' => 'images/backgrounds/default_6.jpg',
        'default_7' => 'images/backgrounds/default_7.jpg',
        'default_8' => 'images/backgrounds/default_8.jpg',
    ];

    /**
     * Get all available default backgrounds
     */
    public function getDefaultBackgrounds(): array
    {
        return array_map(function ($path, $key) {
            return [
                'key' => $key,
                'url' => asset($path),
                'path' => $path,
            ];
        }, self::DEFAULT_BACKGROUNDS, array_keys(self::DEFAULT_BACKGROUNDS));
    }
    /**
     * Get the logo URL for a display
     */
    public function getLogoUrl(Display $display): ?string
    {
        $logo = DisplaySettings::getLogo($display);
        if (!$logo) {
            return null;
        }

        // Add version parameter based on when logo was last updated
        $version = $this->getImageVersion($display, 'logo');
        return url('api/displays/' . $display->id . '/images/logo') . '?v=' . $version;
    }

    /**
     * Get the background image URL for a display
     */
    public function getBackgroundImageUrl(Display $display): ?string
    {
        $background = DisplaySettings::getBackgroundImage($display);
        if (!$background) {
            return null;
        }

        // Check if it's a default background - if so, return the direct asset URL
        if (isset(self::DEFAULT_BACKGROUNDS[$background])) {
            return asset(self::DEFAULT_BACKGROUNDS[$background]);
        }

        // Add version parameter based on when background was last updated
        $version = $this->getImageVersion($display, 'background');
        return url('api/displays/' . $display->id . '/images/background') . '?v=' . $version;
    }

    /**
     * Get image version based on file modification time or fallback to display updated_at
     */
    private function getImageVersion(Display $display, string $type): string
    {
        $imagePath = $type === 'logo'
            ? DisplaySettings::getLogo($display)
            : DisplaySettings::getBackgroundImage($display);

        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            // Use file modification time as version
            return (string) Storage::disk('public')->lastModified($imagePath);
        }

        // Fallback to display updated_at timestamp
        return $display->updated_at->timestamp;
    }

    /**
     * Serve a display image (logo or background)
     */
    public function serveImage(Display $display, string $type)
    {
        if ($type === 'logo') {
            $imagePath = DisplaySettings::getLogo($display);
        } elseif ($type === 'background') {
            $imagePath = DisplaySettings::getBackgroundImage($display);

            // Check if it's a default background
            if ($imagePath && isset(self::DEFAULT_BACKGROUNDS[$imagePath])) {
                $publicPath = public_path(self::DEFAULT_BACKGROUNDS[$imagePath]);
                if (file_exists($publicPath)) {
                    return response()->file($publicPath);
                }
            }
        } else {
            abort(404, 'Invalid image type');
        }

        if (!$imagePath || !Storage::disk('public')->exists($imagePath)) {
            abort(404, 'Image not found');
        }

        return response()->file(Storage::disk('public')->path($imagePath));
    }

    /**
     * Store a logo file and return the path
     */
    public function storeLogoFile($file, Display $display): ?string
    {
        try {
            $filename = 'logo_' . $display->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('displays/logos', $filename, 'public');
            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Store a background image file and return the path
     */
    public function storeBackgroundImageFile($file, Display $display): ?string
    {
        try {
            $filename = 'background_' . $display->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('displays/backgrounds', $filename, 'public');
            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Remove logo file from storage
     */
    public function removeLogoFile(Display $display): void
    {
        $currentLogo = DisplaySettings::getLogo($display);
        if ($currentLogo && Storage::disk('public')->exists($currentLogo)) {
            Storage::disk('public')->delete($currentLogo);
        }
    }

    /**
     * Remove background image file from storage
     */
    public function removeBackgroundImageFile(Display $display): void
    {
        $currentBackground = DisplaySettings::getBackgroundImage($display);
        if ($currentBackground && Storage::disk('public')->exists($currentBackground)) {
            Storage::disk('public')->delete($currentBackground);
        }
    }

    /**
     * Get the logo URL for a board
     */
    public function getBoardLogoUrl(\App\Models\Board $board): ?string
    {
        if (!$board->logo) {
            return null;
        }

        // Add version parameter based on when logo was last updated
        $version = $board->updated_at->timestamp;
        return url('boards/' . $board->id . '/images/logo') . '?v=' . $version;
    }

    /**
     * Store a logo file for a board and return the path
     */
    public function storeBoardLogoFile($file, \App\Models\Board $board): ?string
    {
        try {
            $filename = 'logo_' . $board->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('boards/logos', $filename, 'public');
            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Remove logo file from storage for a board
     */
    public function removeBoardLogoFile(\App\Models\Board $board): void
    {
        if ($board->logo && Storage::disk('public')->exists($board->logo)) {
            Storage::disk('public')->delete($board->logo);
        }
    }

    /**
     * Serve board logo image
     */
    public function serveBoardLogo(\App\Models\Board $board)
    {
        if (!$board->logo || !Storage::disk('public')->exists($board->logo)) {
            abort(404, 'Logo not found');
        }

        return response()->file(Storage::disk('public')->path($board->logo));
    }
}
