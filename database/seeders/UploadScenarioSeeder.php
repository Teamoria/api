<?php

namespace Database\Seeders;

use App\Enums\FileCategory;
use App\Enums\UploadAccessLevel;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use App\Models\Company;
use App\Models\ExtractedDecision;
use App\Models\KnowledgeChunk;
use App\Models\MeetingSummary;
use App\Models\Project;
use App\Models\Task;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Seeder;

class UploadScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()
            ->where('name', 'Teamoria Demo')
            ->sole();
        $project = Project::query()
            ->whereBelongsTo($company)
            ->where('name', 'Active Product Launch')
            ->sole();
        $task = Task::query()
            ->whereBelongsTo($project)
            ->where('title', 'Build project dashboard')
            ->sole();
        $owner = $this->findUser('owner@teamoria.test');
        $manager = $this->findUser('manager@teamoria.test');
        $member = $this->findUser('member@teamoria.test');
        $viewer = $this->findUser('viewer@teamoria.test');
        $basePath = "uploads/{$company->id}";

        $this->seedUpload(
            "{$basePath}/personal/documents/private-roadmap.pdf",
            [
                'company_id' => $company->id,
                'project_id' => null,
                'task_id' => null,
                'user_id' => $member->id,
                'scope' => UploadScope::PERSONAL,
                'visibility' => UploadVisibility::PRIVATE,
                'file_name' => 'private-roadmap.pdf',
                'file_type' => 'application/pdf',
                'category' => FileCategory::DOCUMENT,
                'file_size' => 245760,
                'status' => UploadStatus::SUCCESS,
                'upload_date' => now()->subDays(4),
            ],
        );

        $this->seedUpload(
            "{$basePath}/company/images/company-logo.png",
            [
                'company_id' => $company->id,
                'project_id' => null,
                'task_id' => null,
                'user_id' => $owner->id,
                'scope' => UploadScope::COMPANY,
                'visibility' => UploadVisibility::MEMBERS,
                'file_name' => 'company-logo.png',
                'file_type' => 'image/png',
                'category' => FileCategory::IMAGE,
                'file_size' => 184320,
                'status' => UploadStatus::SUCCESS,
                'upload_date' => now()->subWeek(),
            ],
        );

        $this->seedUpload(
            "{$basePath}/project/videos/product-demo.mp4",
            [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'task_id' => null,
                'user_id' => $manager->id,
                'scope' => UploadScope::PROJECT,
                'visibility' => UploadVisibility::MEMBERS,
                'file_name' => 'product-demo.mp4',
                'file_type' => 'video/mp4',
                'category' => FileCategory::VIDEO,
                'file_size' => 15728640,
                'status' => UploadStatus::UPLOADING,
                'upload_date' => null,
            ],
        );

        $meetingUpload = $this->seedUpload(
            "{$basePath}/task/audio/sprint-planning.mp3",
            [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'task_id' => $task->id,
                'user_id' => $manager->id,
                'scope' => UploadScope::TASK,
                'visibility' => UploadVisibility::MEMBERS,
                'file_name' => 'sprint-planning.mp3',
                'file_type' => 'audio/mpeg',
                'category' => FileCategory::AUDIO,
                'file_size' => 5242880,
                'status' => UploadStatus::SUCCESS,
                'upload_date' => now()->subDay(),
            ],
        );

        $selectedProjectUpload = $this->seedUpload(
            "{$basePath}/project/documents/failed-budget.xlsx",
            [
                'company_id' => $company->id,
                'project_id' => $project->id,
                'task_id' => null,
                'user_id' => $member->id,
                'scope' => UploadScope::PROJECT,
                'visibility' => UploadVisibility::SELECTED,
                'file_name' => 'failed-budget.xlsx',
                'file_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'category' => FileCategory::DOCUMENT,
                'file_size' => 0,
                'status' => UploadStatus::FAILED,
                'upload_date' => null,
            ],
        );

        $selectedCompanyUpload = $this->seedUpload(
            "{$basePath}/company/images/pending-banner.jpg",
            [
                'company_id' => $company->id,
                'project_id' => null,
                'task_id' => null,
                'user_id' => $owner->id,
                'scope' => UploadScope::COMPANY,
                'visibility' => UploadVisibility::SELECTED,
                'file_name' => 'pending-banner.jpg',
                'file_type' => 'image/jpeg',
                'category' => FileCategory::IMAGE,
                'file_size' => 0,
                'status' => UploadStatus::PENDING,
                'upload_date' => null,
            ],
        );

        $selectedProjectUpload->sharedUsers()->sync([
            $viewer->id => [
                'access_level' => UploadAccessLevel::VIEW->value,
                'granted_by' => $manager->id,
            ],
        ]);

        $selectedCompanyUpload->sharedUsers()->sync([
            $manager->id => [
                'access_level' => UploadAccessLevel::MANAGE->value,
                'granted_by' => $owner->id,
            ],
        ]);

        $this->seedMeetingKnowledge($meetingUpload, $project);
    }

    private function seedMeetingKnowledge(Upload $meetingUpload, Project $project): void
    {
        $meetingSummary = MeetingSummary::query()->updateOrCreate(
            ['upload_id' => $meetingUpload->id],
            [
                'transcript' => 'The team reviewed launch progress, dashboard work, and release blockers.',
                'summary' => 'Dashboard development is progressing, while the staging release remains blocked.',
            ],
        );

        foreach ([
            'Complete the dashboard empty states before review.',
            'Keep the staging release blocked until dashboard approval.',
            'Schedule the next progress review for Monday.',
        ] as $decision) {
            ExtractedDecision::query()->updateOrCreate([
                'meeting_summary_id' => $meetingSummary->id,
                'decision_text' => $decision,
            ]);
        }

        KnowledgeChunk::query()->updateOrCreate(
            [
                'upload_id' => $meetingUpload->id,
                'content' => 'The dashboard widgets are under active development.',
            ],
            [
                'project_id' => $project->id,
                'embedding' => [0.12, 0.41, 0.73],
                'metadata' => ['source' => 'meeting', 'segment' => 1],
            ],
        );

        KnowledgeChunk::query()->updateOrCreate(
            [
                'upload_id' => $meetingUpload->id,
                'content' => 'The staging release depends on dashboard approval.',
            ],
            [
                'project_id' => $project->id,
                'embedding' => [0.82, 0.26, 0.34],
                'metadata' => ['source' => 'meeting', 'segment' => 2],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedUpload(string $filePath, array $attributes): Upload
    {
        return Upload::query()->updateOrCreate(
            ['file_path' => $filePath],
            [...$attributes, 'file_path' => $filePath],
        );
    }

    private function findUser(string $email): User
    {
        return User::query()
            ->where('email', $email)
            ->sole();
    }
}
