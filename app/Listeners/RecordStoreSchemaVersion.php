<?php

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Events\DatabaseMigrated;

/**
 * Keeps `stores.schema_version` equal to the latest migration of the store's database, whoever ran
 * `tenants:migrate` (the provisioner or a deploy). It runs while that store's tenancy is initialized.
 */
class RecordStoreSchemaVersion
{
    public function handle(DatabaseMigrated $event): void
    {
        $latestMigration = DB::table('migrations')->orderByDesc('id')->value('migration');

        $event->tenant->forceFill(['schema_version' => $latestMigration])->save();
    }
}
