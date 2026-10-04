<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\ImportCursor;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Enums\RowFlag;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Import\ImportRowValidator;
use App\Services\Import\ImportStep;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use DatabaseTransactions;

    private const FIXTURE = __DIR__ . '/../fixtures/leads-sample.csv';

    /**
     * What each line of the fixture must become: file line => [status, flags].
     */
    private const EXPECTED = [
        2 => [ImportRowStatus::Clean, []],
        3 => [ImportRowStatus::Error, [RowFlag::Phone]],
        4 => [ImportRowStatus::Warning, [RowFlag::Email]],
        5 => [ImportRowStatus::Warning, [RowFlag::Email]],
        6 => [ImportRowStatus::Warning, [RowFlag::FirstName]],
        7 => [ImportRowStatus::Clean, []],
        8 => [ImportRowStatus::Warning, [RowFlag::Status]],
        9 => [ImportRowStatus::Error, [RowFlag::Columns]],
        10 => [ImportRowStatus::Clean, []],
        11 => [ImportRowStatus::Warning, [RowFlag::CreatedAt]],
        13 => [ImportRowStatus::Clean, []],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_every_line_of_the_file_is_stored_with_its_status_and_flags(): void
    {
        $import = $this->runToEnd($this->uploadedImport((string) file_get_contents(self::FIXTURE)));

        $this->assertSame(ImportStatus::Done, $import->status);
        $this->assertSame(11, $import->rows_total);
        $this->assertSame(4, $import->rows_clean);
        $this->assertSame(5, $import->rows_warning);
        $this->assertSame(2, $import->rows_error);
        $this->assertNotNull($import->finished_at);

        $rows = $import->rows()->orderBy('row_number')->get()->keyBy('row_number');
        $this->assertSame(array_keys(self::EXPECTED), $rows->keys()->all());

        foreach (self::EXPECTED as $line => [$status, $flags]) {
            $this->assertSame($status, $rows[$line]->status, "line $line status");
            $this->assertSame($flags, $rows[$line]->problems(), "line $line flags");
        }

        $this->assertSame("23\u{00A0}700", $rows[7]->budget_uah);
        $this->assertSame('Клієнт постійний, повторне звернення', $rows[10]->comment);
        $this->assertNull($rows[9]->next_contact_at);
        $this->assertSame('2025-07-10 05:06:54', $rows[2]->created_at_raw);
        $this->assertSame('LD-000001', $rows[13]->external_id);

        Storage::disk('local')->assertMissing($import->filePath());
    }

    public function test_a_step_with_its_time_spent_still_reads_a_row_and_the_result_matches_one_step(): void
    {
        $content = (string) file_get_contents(self::FIXTURE);
        $step = app(ImportStep::class);

        $inOneStep = $this->runToEnd($this->uploadedImport($content));

        $inManySteps = $this->uploadedImport($content);
        $cursor = new ImportCursor();
        $steps = 0;
        do {
            $inManySteps = $step->run($inManySteps->id, $cursor, 0.0);
            $steps++;
        } while ($inManySteps->status === ImportStatus::Processing && $steps < 50);

        $this->assertSame(ImportStatus::Done, $inManySteps->status);
        $this->assertGreaterThanOrEqual(11, $steps);
        $this->assertSame($this->rowsOf($inOneStep), $this->rowsOf($inManySteps));
        $this->assertSame(
            [$inOneStep->rows_total, $inOneStep->rows_clean, $inOneStep->rows_warning, $inOneStep->rows_error],
            [$inManySteps->rows_total, $inManySteps->rows_clean, $inManySteps->rows_warning, $inManySteps->rows_error],
        );
    }

    public function test_a_wrong_header_fails_the_import_and_stores_no_rows(): void
    {
        $import = $this->runToEnd($this->uploadedImport("id,name,phone\n1,Олександр,380672341057\n"));

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertStringContainsString('Невірний заголовок файлу', (string) $import->error);
        $this->assertSame(0, $import->rows()->count());
    }

    public function test_a_finished_import_is_not_read_again(): void
    {
        $import = $this->runToEnd($this->uploadedImport((string) file_get_contents(self::FIXTURE)));

        $again = app(ImportStep::class)->run($import->id, new ImportCursor());

        $this->assertSame(ImportStatus::Done, $again->status);
        $this->assertSame(11, $again->rows()->count());
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/imports')->assertRedirect(route('login'));
        $this->postJson('/imports', [])->assertUnauthorized();
    }

    public function test_a_user_logs_in(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('imports.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_file_goes_through_upload_and_processing_over_http(): void
    {
        $user = User::factory()->create();
        $content = (string) file_get_contents(self::FIXTURE);

        $created = $this->actingAs($user)
            ->post('/imports', ['file' => $this->csvFile($content)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJson(['status' => 'processing', 'rows_total' => 0]);
        $id = $created->json('id');

        $this->assertSame($content, Storage::disk('local')->get("imports/$id.csv"));

        $state = [];
        for ($i = 0; $i < 20; $i++) {
            $step = $this->postJson("/imports/$id/process")->assertOk();
            $state = $step->json();
            if ($state['finished']) {
                $step->assertSessionMissing('import.cursor.'.$id);
                break;
            }
        }
        $this->assertSame('done', $state['status']);
        $this->assertSame([11, 4, 5, 2], [$state['rows_total'], $state['rows_clean'], $state['rows_warning'], $state['rows_error']]);

        $this->get('/imports')->assertOk()->assertSee('leads-sample.csv');
        $this->get("/imports/$id")->assertOk()->assertSee('leads-sample.csv');

        $rows = $this->getJson("/imports/$id/rows")->assertOk()->json();
        $this->assertCount(11, $rows['data']);
        $this->assertSame([11, 1, 1, 50], [
            $rows['meta']['total'],
            $rows['meta']['current_page'],
            $rows['meta']['last_page'],
            $rows['meta']['per_page'],
        ]);
        $this->assertSame(3, $rows['data'][1]['row_number']);
        $this->assertSame(['Невірний телефон'], $rows['data'][1]['problems']);
        $this->assertContains('#ERROR!', $rows['data'][1]['values']);
        $this->assertNotEmpty($rows['flag_counts']);

        $errors = $this->getJson("/imports/$id/rows?status=error")->assertOk()->json();
        $this->assertSame(2, $errors['meta']['total']);

        $export = $this->get("/imports/$id/export")->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}row,status,flags,external_id,", $export);
        $lines = array_values(array_filter(explode("\n", trim($export))));
        $this->assertCount(1 + 11, $lines);
        $this->assertStringStartsWith('3,Помилка,"Невірний телефон",LD-000002,', $lines[2]);
    }

    public function test_each_step_continues_from_the_position_kept_in_the_session(): void
    {
        $this->app->instance(ImportStep::class, new ImportStep(app(ImportRowValidator::class), 0.0));

        $content = (string) file_get_contents(self::FIXTURE);
        $id = $this->actingAs(User::factory()->create())
            ->post('/imports', ['file' => $this->csvFile($content)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('id');

        $first = $this->postJson("/imports/$id/process")->assertOk()->assertSessionHas('import.cursor.'.$id);
        $this->assertFalse($first->json('finished'));
        $this->assertSame(1, $first->json('rows_total'));

        $second = $this->postJson("/imports/$id/process")->assertOk()->json();
        $this->assertSame(2, $second['rows_total']);
        $this->assertSame(
            [2, 3],
            ImportRow::query()->where('import_id', $id)->orderBy('row_number')->pluck('row_number')->all(),
        );
    }

    public function test_only_a_csv_file_is_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/imports', ['file' => UploadedFile::fake()->create('leads.xlsx', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Підтримується лише файл .csv.']);
    }

    private function uploadedImport(string $content): Import
    {
        $import = Import::query()->create([
            'user_id' => User::factory()->create()->id,
            'file_name' => 'leads-sample.csv',
            'file_size' => strlen($content),
            'status' => ImportStatus::Processing,
        ]);

        Storage::disk('local')->put($import->filePath(), $content);

        return $import->refresh();
    }

    private function runToEnd(Import $import): Import
    {
        $step = app(ImportStep::class);
        $cursor = new ImportCursor();
        for ($i = 0; $i < 20 && $import->status === ImportStatus::Processing; $i++) {
            $import = $step->run($import->id, $cursor);
        }

        return $import->refresh();
    }

    /**
     * The stored rows without the columns that differ between two imports of the same file.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsOf(Import $import): array
    {
        return $import->rows()
            ->orderBy('row_number')
            ->get()
            ->map(static fn (ImportRow $row): array => collect($row->getAttributes())
                ->except(['id', 'import_id', 'created_at', 'updated_at'])
                ->all())
            ->all();
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('leads-sample.csv', $content);
    }
}
