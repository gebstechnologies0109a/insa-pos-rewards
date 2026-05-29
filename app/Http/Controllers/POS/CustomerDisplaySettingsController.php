<?php

namespace App\Http\Controllers\POS;

use App\Http\Controllers\Controller;
use App\Services\POS\PosSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerDisplaySettingsController extends Controller
{
    public function __construct(
        protected PosSettingsService $settings,
    ) {}

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $file = $request->file('photo');
        $name = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $dir = $this->mediaDir('photos');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        $url = route('pos.customer-display.media', ['type' => 'photos', 'filename' => $name]);
        $this->settings->set('customer_display.photo', $url);

        return response()->json([
            'success' => true,
            'message' => 'Photo uploaded.',
            'url'     => $url,
            'path'    => "customer-display/photos/{$name}",
        ]);
    }

    public function uploadVideo(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimes:mp4|max:51200',
        ]);

        $file = $request->file('video');
        $name = Str::uuid()->toString() . '.mp4';
        $dir = $this->mediaDir('videos');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $name);

        $url = route('pos.customer-display.media', ['type' => 'videos', 'filename' => $name]);
        $this->settings->set('customer_display.video', $url);

        return response()->json([
            'success' => true,
            'message' => 'Video uploaded.',
            'url'     => $url,
            'path'    => "customer-display/videos/{$name}",
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled'        => 'nullable|boolean',
            'orientation'    => 'nullable|string|in:portrait,landscape,auto',
            'rotation_mode'  => 'nullable|string|in:mix,photos,videos,loop_photos,loop_videos',
            'show_cart'      => 'nullable|boolean',
            'photo'          => 'nullable|string|max:500',
            'video'          => 'nullable|string|max:500',
        ]);

        $map = [
            'enabled'       => 'customer_display.enabled',
            'orientation'   => 'customer_display.orientation',
            'rotation_mode' => 'customer_display.rotation_mode',
            'show_cart'     => 'customer_display.show_cart',
            'photo'         => 'customer_display.photo',
            'video'         => 'customer_display.video',
        ];

        foreach ($map as $input => $key) {
            if (! array_key_exists($input, $validated)) {
                continue;
            }
            $value = $validated[$input];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $this->settings->set($key, (string) $value);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Customer display settings saved.',
            'settings' => $this->customerDisplayMap(),
        ]);
    }

    public function serveMedia(string $type, string $filename): mixed
    {
        if (! in_array($type, ['photos', 'videos'], true)) {
            abort(404);
        }
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            abort(404);
        }

        $path = $this->mediaDir($type) . DIRECTORY_SEPARATOR . $filename;
        if (! is_file($path)) {
            abort(404);
        }

        $mime = $type === 'videos'
            ? 'video/mp4'
            : (match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'application/octet-stream',
            });

        return response()->file($path, ['Content-Type' => $mime]);
    }

    private function mediaDir(string $type): string
    {
        return storage_path("app/customer-display/{$type}");
    }

    /**
     * @return array<string, string>
     */
    public function customerDisplayMap(): array
    {
        return [
            'enabled'       => $this->settings->get('customer_display.enabled'),
            'photo'         => $this->settings->get('customer_display.photo'),
            'video'         => $this->settings->get('customer_display.video'),
            'orientation'   => $this->settings->get('customer_display.orientation') ?: 'auto',
            'rotation_mode' => $this->settings->get('customer_display.rotation_mode') ?: 'mix',
            'show_cart'     => $this->settings->get('customer_display.show_cart'),
        ];
    }
}
