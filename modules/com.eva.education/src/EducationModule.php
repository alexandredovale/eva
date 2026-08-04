<?php

declare(strict_types=1);

namespace EvaModule\Education;

use Eva\ModuleRuntime\DashboardModuleInterface;
use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleInterface;
use EvaModule\Education\Dashboard\EducationReadService;
use EvaModule\Education\Dashboard\EducationDashboardPresenter;
use EvaModule\Education\Governance\GovernancePolicy;
use EvaModule\Education\Interpreter\LearningInterpreter;
use EvaModule\Education\Observer\LearningObserver;
use EvaModule\Education\Storage\EducationSchema;

final class EducationModule implements ModuleInterface, DashboardModuleInterface
{
    public function id(): string
    {
        return 'com.eva.education';
    }

    public function install(ModuleContext $context): void
    {
        (new EducationSchema())->install($context->storage);
        $governance = (new GovernancePolicy())->validate([]);
        $statement = $context->storage->prepare(
            "INSERT OR REPLACE INTO module_settings (setting_key, value_json, updated_at)
             VALUES ('governance', :value_json, datetime('now'))"
        );
        $statement->execute([
            'value_json' => json_encode($governance, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function handle(ModuleEvent $event, ModuleContext $context): void
    {
        (new LearningObserver())->observe($event, $context->storage);
        (new LearningInterpreter())->processEvent($context, $event->eventId);
    }

    public function dashboard(ModuleContext $context, array $actor, array $filters): array
    {
        $this->install($context);
        $dashboard = (new EducationReadService())->read($context->storage, $actor, $filters);
        $users = $actor['role'] === 'superadmin' ? $context->core->users() : [];

        return (new EducationDashboardPresenter())->present($dashboard, $actor, $users);
    }

}
