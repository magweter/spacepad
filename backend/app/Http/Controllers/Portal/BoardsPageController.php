<?php

namespace App\Http\Controllers\Portal;

use App\Enums\DisplayStatus;
use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Display;
use App\Models\Workspace;
use App\Support\PortalShell;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Boards page: the workspace's boards on the left, the editor of one board on the right.
 *
 * The same page serves the list (GET /boards, the first board open), editing a board
 * (boards.edit) and creating one (boards.create); BoardController hands those two to render().
 * A free workspace gets the page with a Pro upsell instead of an error.
 */
class BoardsPageController extends Controller
{
    public const FONTS = ['Inter', 'Roboto', 'Open Sans', 'Lato', 'Poppins', 'Montserrat'];

    public const LANGUAGES = [
        'en' => 'English',
        'nl' => 'Nederlands',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'es' => 'Español',
        'sv' => 'Svenska',
    ];

    public function index(Request $request): Response
    {
        $workspace = $request->user()->getSelectedWorkspace();
        abort_unless($workspace !== null, 404);

        return $this->render($request, $workspace);
    }

    /**
     * Render the page. Without $editing or $creating it is the overview of the boards as cards;
     * with one of them it is the editor for that board or a new one.
     */
    public function render(Request $request, Workspace $workspace, ?Board $editing = null, bool $creating = false): Response
    {
        $user = $request->user();
        $hasPro = $workspace->hasPro();

        $boards = $hasPro
            ? Board::where('workspace_id', $workspace->id)->with(['user', 'displays'])->orderBy('name')->get()
            : collect();

        $editorWorkspaceId = $editing?->workspace_id ?? $workspace->id;
        $displays = $hasPro
            ? Display::where('workspace_id', $editorWorkspaceId)
                ->whereIn('status', [DisplayStatus::READY, DisplayStatus::ACTIVE])
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Boards/Index', [
            'shell' => PortalShell::for($request, $workspace, 'boards'),
            'hasPro' => $hasPro,
            'canCreate' => $hasPro && $user->can('create', Board::class),
            'boards' => $boards->map(fn (Board $board) => $this->listItem($request, $board))->values()->all(),
            'creating' => $creating,
            'board' => $editing ? $this->formData($editing) : null,
            'workspaceId' => $editorWorkspaceId,
            'displays' => $displays->map(fn (Display $display) => [
                'id' => $display->id,
                'name' => $display->display_name ?: $display->name,
            ])->values()->all(),
            'fonts' => self::FONTS,
            'languages' => collect(self::LANGUAGES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            // Set by store/update when the public link was just switched on.
            'showPublic' => $editing !== null && $editing->is_public && $editing->public_token && $request->boolean('show_public'),
            'urls' => [
                'index' => route('boards.index'),
                'create' => route('boards.create'),
                'store' => route('boards.store'),
                'upgrade' => route('billing.index'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Request $request, Board $board): array
    {
        $user = $request->user();
        // The card draws a sketch of the board instead of loading the live page: twenty live
        // boards in iframes would each fetch every room's calendar. A dozen names is enough to sketch.
        $rooms = $board->getDisplaysToShowQuery()->orderBy('name')->limit(12)->get(['displays.id', 'name', 'display_name']);

        return [
            'id' => $board->id,
            'name' => $board->name,
            'title' => $board->show_title ? ($board->title ?: $board->name) : null,
            'rooms' => $rooms->map(fn (Display $display) => $display->display_name ?: $display->name)->values()->all(),
            'viewMode' => $board->view_mode ?? 'card',
            'theme' => $board->theme ?? 'dark',
            'showAll' => $board->show_all_displays,
            'roomCount' => $board->display_count,
            'isPublic' => $board->is_public && $board->public_token,
            'createdBy' => $board->user?->name,
            'editUrl' => route('boards.edit', $board),
            'showUrl' => route('boards.show', $board),
            'canUpdate' => $user->can('update', $board),
        ];
    }

    /**
     * Everything the editor needs of a saved board, named as the form posts it.
     *
     * @return array<string, mixed>
     */
    private function formData(Board $board): array
    {
        $user = request()->user();

        return [
            'id' => $board->id,
            'name' => $board->name,
            'title' => $board->title,
            'subtitle' => $board->subtitle,
            'show_all_displays' => $board->show_all_displays,
            'display_ids' => $board->displays->pluck('id')->values()->all(),
            'theme' => $board->theme ?? 'dark',
            'view_mode' => $board->view_mode ?? 'card',
            'show_title' => $board->show_title ?? true,
            'show_booker' => $board->show_booker ?? true,
            'show_next_event' => $board->show_next_event ?? true,
            'show_transitioning' => $board->show_transitioning ?? true,
            'transitioning_minutes' => $board->transitioning_minutes ?? 10,
            'font_family' => $board->font_family ?? 'Inter',
            'language' => $board->language ?? 'en',
            'show_meeting_title' => $board->show_meeting_title ?? true,
            'show_join_button' => $board->show_join_button ?? false,
            'is_public' => (bool) $board->is_public,
            'categories' => collect($board->categories ?? [])->map(fn ($category) => [
                'name' => $category['name'] ?? '',
                'display_ids' => array_values($category['display_ids'] ?? []),
            ])->values()->all(),
            'logoUrl' => $board->logo ? route('boards.images.logo', $board).'?v='.$board->updated_at?->timestamp : null,
            'publicUrl' => $board->is_public && $board->public_token ? route('boards.public', $board->public_token) : null,
            'showUrl' => route('boards.show', $board),
            'updateUrl' => route('boards.update', $board),
            'destroyUrl' => route('boards.destroy', $board),
            'canDelete' => $user->can('delete', $board),
            'version' => $board->updated_at?->timestamp,
        ];
    }
}
