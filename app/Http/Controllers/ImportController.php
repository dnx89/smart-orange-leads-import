<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportRowStatus;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use App\Models\ImportRow;
use App\Services\Import\ImportRows;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly ImportRows $rows,
    ) {}

    public function index(): View
    {
        return view('imports.index', ['imports' => $this->imports->history()]);
    }

    public function create(): View
    {
        return view('imports.create', [
            'columns' => ImportRow::fileColumns(),
        ]);
    }

    public function store(StoreImportRequest $request): JsonResponse
    {
        $import = $this->imports->start($request->file('file'), $request->user());

        return ImportResource::make($import)->response()->setStatusCode(201);
    }

    public function show(Import $import): View
    {
        return view('imports.show', [
            'import' => $import->load('user:id,name'),
            'columns' => ImportRow::fileColumns(),
            'statuses' => array_map(
                static fn(ImportRowStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                ImportRowStatus::cases(),
            ),
        ]);
    }

    /**
     * One time-limited step of the processing; the page calls it again until the import finishes.
     */
    public function process(Import $import): JsonResponse
    {
        $result = $this->imports->runStep($import);

        return ImportResource::make($result->import, $result->progress)
            ->response()
            ->setStatusCode($result->crashed ? 500 : 200);
    }

    public function export(Import $import): StreamedResponse
    {
        $name = pathinfo($import->file_name, PATHINFO_FILENAME).'-result-'.$import->id.'.csv';

        return response()->streamDownload(
            fn() => $this->rows->writeCsv($import, fopen('php://output', 'wb')),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
