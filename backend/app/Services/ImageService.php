<?php

namespace App\Services;

use App\Helpers\DisplaySettings;
use App\Helpers\ProfileSettings;
use App\Models\Board;
use App\Models\Display;
use App\Models\DisplayProfile;
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
        if (! $logo) {
            return null;
        }

        // Add version parameter based on when logo was last updated
        $version = $this->getImageVersion($display, 'logo');

        return url('api/displays/'.$display->id.'/images/logo').'?v='.$version;
    }

    /**
     * Get the advertisement image URL for a display
     */
    public function getAdvertisementImageUrl(Display $display): ?string
    {
        $advertisement = DisplaySettings::getAdvertisementImage($display);
        if (! $advertisement) {
            return null;
        }

        $version = $this->getImageVersion($display, 'advertisement');

        return url('api/displays/'.$display->id.'/images/advertisement').'?v='.$version;
    }

    /**
     * Get the background image URL for a display
     */
    public function getBackgroundImageUrl(Display $display): ?string
    {
        $background = DisplaySettings::getBackgroundImage($display);
        if (! $background) {
            return null;
        }

        // Check if it's a default background - if so, return the direct asset URL
        if (isset(self::DEFAULT_BACKGROUNDS[$background])) {
            return asset(self::DEFAULT_BACKGROUNDS[$background]);
        }

        // Add version parameter based on when background was last updated
        $version = $this->getImageVersion($display, 'background');

        return url('api/displays/'.$display->id.'/images/background').'?v='.$version;
    }

    /**
     * Get image version based on file modification time or fallback to display updated_at
     */
    private function getImageVersion(Display $display, string $type): string
    {
        $imagePath = match ($type) {
            'logo' => DisplaySettings::getLogo($display),
            'background' => DisplaySettings::getBackgroundImage($display),
            'advertisement' => DisplaySettings::getAdvertisementImage($display),
            default => null,
        };

        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            // Use file modification time as version
            return (string) Storage::disk('public')->lastModified($imagePath);
        }

        // Fallback to display updated_at timestamp
        return $display->updated_at->timestamp;
    }

    /**
     * Serve a display image (logo, background, or advertisement)
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
        } elseif ($type === 'advertisement') {
            $imagePath = DisplaySettings::getAdvertisementImage($display);
        } else {
            abort(404, 'Invalid image type');
        }

        if (! $imagePath || ! Storage::disk('public')->exists($imagePath)) {
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
            $filename = 'logo_'.$display->id.'_'.time().'.'.$file->getClientOriginalExtension();
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
            $filename = 'background_'.$display->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('displays/backgrounds', $filename, 'public');

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Store an advertisement image file and return the path
     */
    public function storeAdvertisementFile($file, Display $display): ?string
    {
        try {
            $filename = 'advertisement_'.$display->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('displays/advertisements', $filename, 'public');

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Remove the display's own logo file from storage.
     */
    public function removeLogoFile(Display $display): void
    {
        $this->deleteOwnedFile($display, 'logo');
    }

    /**
     * Remove the display's own background image file from storage.
     */
    public function removeBackgroundImageFile(Display $display): void
    {
        $this->deleteOwnedFile($display, 'background_image');
    }

    /**
     * Remove the display's own advertisement image file from storage.
     */
    public function removeAdvertisementFile(Display $display): void
    {
        $this->deleteOwnedFile($display, 'advertisement_image');
    }

    /**
     * Delete an uploaded file only when the display owns it.
     *
     * Reading the resolved value would return the linked profile's path when the display inherits the
     * image — deleting that file would strip the image from every display following that profile.
     * Bundled default backgrounds are keys, not stored files, so they are skipped too.
     */
    private function deleteOwnedFile(Display $display, string $key): void
    {
        $path = DisplaySettings::getOwnSetting($display, $key);

        if (! $path || isset(self::DEFAULT_BACKGROUNDS[$path])) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Store an image for a profile and return its path.
     *
     * Profile images live in their own folders so that a display inheriting one resolves to the
     * profile's file, while a display that uploads its own keeps a separate file.
     */
    public function storeProfileImageFile($file, DisplayProfile $profile, string $type): ?string
    {
        $folder = match ($type) {
            'logo' => 'profiles/logos',
            'background' => 'profiles/backgrounds',
            'advertisement' => 'profiles/advertisements',
            default => null,
        };

        if ($folder === null) {
            return null;
        }

        try {
            $filename = $type.'_'.$profile->id.'_'.time().'.'.$file->getClientOriginalExtension();

            return $file->storeAs($folder, $filename, 'public');
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * Remove a profile's stored image file. Bundled default backgrounds are keys, not files.
     */
    public function removeProfileImageFile(DisplayProfile $profile, string $key): void
    {
        $path = ProfileSettings::get($profile, $key);

        if (! $path || isset(self::DEFAULT_BACKGROUNDS[$path])) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Serve a profile image, used for the previews on the profile form.
     */
    public function serveProfileImage(DisplayProfile $profile, string $type)
    {
        $key = match ($type) {
            'logo' => 'logo',
            'background' => 'background_image',
            'advertisement' => 'advertisement_image',
            default => abort(404, 'Invalid image type'),
        };

        $path = ProfileSettings::get($profile, $key);

        if ($path && isset(self::DEFAULT_BACKGROUNDS[$path])) {
            $publicPath = public_path(self::DEFAULT_BACKGROUNDS[$path]);
            if (file_exists($publicPath)) {
                return response()->file($publicPath);
            }
        }

        if (! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'Image not found');
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * Get the logo URL for a board
     */
    public function getBoardLogoUrl(Board $board): ?string
    {
        if (! $board->logo) {
            return null;
        }

        // Add version parameter based on when logo was last updated
        $version = $board->updated_at->timestamp;

        return url('boards/'.$board->id.'/images/logo').'?v='.$version;
    }

    /**
     * Store a logo file for a board and return the path
     */
    public function storeBoardLogoFile($file, Board $board): ?string
    {
        try {
            $filename = 'logo_'.$board->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('boards/logos', $filename, 'public');

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Remove logo file from storage for a board
     */
    public function removeBoardLogoFile(Board $board): void
    {
        if ($board->logo && Storage::disk('public')->exists($board->logo)) {
            Storage::disk('public')->delete($board->logo);
        }
    }

    /**
     * Serve board logo image
     */
    public function serveBoardLogo(Board $board)
    {
        if (! $board->logo || ! Storage::disk('public')->exists($board->logo)) {
            abort(404, 'Logo not found');
        }

        return response()->file(Storage::disk('public')->path($board->logo));
    }
}
