<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreController extends Controller
{
    public function products(): JsonResponse
    {
        return response()->json(['products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => array_merge(json_decode($row->data, true), ['id' => $row->id, 'slug' => $row->slug]))]);
    }

    public function submitSupport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);
        $id = DB::table('support_tickets')->insertGetId($data + ['status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'message' => 'Support request received.'], 201);
    }

    public function updateSupport(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_unless(DB::table('support_tickets')->where('id', $id)->exists(), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'pending', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'urgent'])],
            'admin_note' => ['nullable', 'string', 'max:10000'],
            'admin_reply' => ['nullable', 'string', 'max:10000'],
        ]);
        DB::table('support_tickets')->where('id', $id)->update($data + ['responded_at' => $data['admin_reply'] ? now() : null, 'updated_at' => now()]);

        return response()->json(['message' => 'Support ticket updated.']);
    }

    public function news(): JsonResponse
    {
        return response()->json(['news' => DB::table('news')->latest('published_at')->get()->map(fn ($row) => $this->newsPayload($row))]);
    }

    public function newsPost(string $slug): JsonResponse
    {
        $post = DB::table('news')->where('slug', $slug)->first();
        abort_if(! $post, 404);

        return response()->json(['post' => $this->newsPayload($post)]);
    }

    public function save(Request $request, ?int $id = null): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);
        if ($id !== null) {
            abort_unless(DB::table('products')->where('id', $id)->exists(), 404);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:200', Rule::unique('products', 'slug')->ignore($id)], 'category' => ['required', 'string', 'max:80'], 'picture' => ['required', 'string', 'max:2000', 'regex:~^(https?://|/(?!/))~'], 'description' => ['required', 'string', 'max:10000'], 'minPrice' => ['required', 'numeric', 'min:0.01', 'max:1000000'], 'maxPrice' => ['required', 'numeric', 'gte:minPrice', 'max:1000000'], 'isGiftCard' => ['required', 'boolean']]);
        $existing = $id ? json_decode(DB::table('products')->where('id', $id)->value('data'), true) : ['rating' => 0, 'reviewsCount' => 0, 'discountTag' => null];
        $packageData = $request->validate([
            'packages' => ['sometimes', 'array', 'max:200'],
            'packages.*' => ['array:id,name,price'],
            'packages.*.id' => ['required', 'string', 'max:100', 'distinct'],
            'packages.*.name' => ['required', 'string', 'max:200'],
            'packages.*.price' => ['required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'],
        ]);
        $data = array_merge($data, $packageData);
        $packages = $data['packages'] ?? $existing['packages'] ?? [];
        if (count($packages) > 0) {
            $data['minPrice'] = min(array_column($packages, 'price'));
            $data['maxPrice'] = max(array_column($packages, 'price'));
        }
        $values = ['slug' => $data['slug'], 'data' => json_encode(array_merge($existing, $data), JSON_THROW_ON_ERROR), 'updated_at' => now()];
        if ($id) {
            DB::table('products')->where('id', $id)->update($values);
        } else {
            $id = DB::table('products')->insertGetId($values + ['created_at' => now()]);
        }

        return response()->json(['id' => $id], $request->isMethod('post') ? 201 : 200);
    }

    public function account(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        return response()->json(['orders' => DB::table('orders')->where('user_id', $id)->latest()->limit(100)->get(), 'walletEntries' => DB::table('wallet_entries')->where('user_id', $id)->latest()->limit(100)->get(), 'balanceCentavos' => (int) DB::table('wallet_entries')->where('user_id', $id)->sum('amount_centavos')]);
    }

    public function admin(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        return response()->json(['users' => DB::table('users')->select('id', 'name', 'email', 'is_admin', 'is_affiliate', 'is_streamer', 'is_auction', 'is_disabled', 'created_at')->latest()->limit(100)->get(), 'news' => DB::table('news')->latest('published_at')->limit(100)->get()->map(fn ($row) => $this->newsPayload($row)), 'support' => DB::table('support_tickets')->latest()->limit(100)->get(), 'orders' => DB::table('orders')->latest()->limit(100)->get(), 'payments' => DB::table('payments')->latest()->limit(100)->get(), 'counts' => ['users' => DB::table('users')->count(), 'orders' => DB::table('orders')->count(), 'revenueCentavos' => (int) DB::table('payments')->where('status', 'completed')->sum('amount_centavos')]]);
    }

    public function report(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $filters = $request->validate(['month' => ['required', 'integer', 'between:1,12'], 'year' => ['required', 'integer', 'between:2000,2100']]);
        $start = CarbonImmutable::create($filters['year'], $filters['month'], 1, 0, 0, 0, 'Asia/Manila')->utc();
        $end = $start->setTimezone('Asia/Manila')->addMonth()->utc();
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay()->utc();
        $summarize = function ($from, $to): array {
            return ['orders' => DB::table('orders')->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(), 'turnoverCentavos' => (int) DB::table('payments')->where('status', 'completed')->where('updated_at', '>=', $from)->where('updated_at', '<', $to)->sum('amount_centavos')];
        };

        return response()->json(['period' => $summarize($start, $end), 'today' => $summarize($today, $today->addDay()), 'pending' => DB::table('orders')->where('status', 'pending')->count(), 'users' => DB::table('users')->count(), 'newUsers' => DB::table('users')->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(), 'products' => DB::table('products')->count()]);
    }

    public function saveNews(Request $request, ?int $id = null): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:255', Rule::unique('news', 'slug')->ignore($id)],
            'category' => ['required', Rule::in(['Esports', 'Patch Notes', 'Guides', 'News'])],
            'author' => ['required', 'string', 'max:255'],
            'read_time' => ['required', 'string', 'max:50'],
            'image' => ['required', 'string', 'max:2000', 'regex:~^(https?://|/(?!/))~'],
            'summary' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'array', 'min:1'],
            'content.*' => ['required', 'string', 'max:10000'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['required', 'string', 'max:50'],
            'published_at' => ['required', 'date'],
        ]);
        $values = ['slug' => $data['slug'], 'title' => $data['title'], 'category' => $data['category'], 'author' => $data['author'], 'read_time' => $data['read_time'], 'image' => $data['image'], 'summary' => $data['summary'], 'content' => json_encode(array_values($data['content']), JSON_THROW_ON_ERROR), 'tags' => json_encode(array_values($data['tags']), JSON_THROW_ON_ERROR), 'published_at' => $data['published_at'], 'updated_at' => now()];
        if ($id) {
            abort_unless(DB::table('news')->where('id', $id)->exists(), 404);
            DB::table('news')->where('id', $id)->update($values);
        } else {
            $id = DB::table('news')->insertGetId($values + ['created_at' => now()]);
        }

        return response()->json(['id' => $id], $id && $request->isMethod('post') ? 201 : 200);
    }

    public function deleteNews(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_unless(DB::table('news')->where('id', $id)->delete(), 404);

        return response()->json(null, 204);
    }

    private function newsPayload(object $row): array
    {
        return ['id' => (string) $row->id, 'slug' => $row->slug, 'title' => $row->title, 'category' => $row->category, 'author' => $row->author, 'date' => date('M d, Y', strtotime($row->published_at)), 'readTime' => $row->read_time, 'image' => $row->image, 'summary' => $row->summary, 'content' => json_decode($row->content, true), 'tags' => json_decode($row->tags, true)];
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $target = DB::table('users')->where('id', $id)->first();
        abort_if(! $target, 404);
        abort_if($target->id === $request->user()->id && $request->boolean('is_disabled'), 422, 'You cannot disable your own administrator account.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'is_affiliate' => ['required', 'boolean'],
            'is_streamer' => ['required', 'boolean'],
            'is_auction' => ['required', 'boolean'],
            'is_disabled' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ]);

        $updates = collect($data)->except('password')->all();
        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }
        $updates['updated_at'] = now();
        DB::table('users')->where('id', $id)->update($updates);

        return response()->json(['message' => 'User updated successfully.']);
    }
}
