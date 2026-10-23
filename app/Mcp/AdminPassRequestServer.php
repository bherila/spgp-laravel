<?php

namespace App\Mcp;

use App\Mcp\Tools\PassRequestTool;
use App\Services\PassRequests\PassRequestOperations;
use Laravel\Mcp\Server;

class AdminPassRequestServer extends Server
{
    protected string $name = 'SPGP Administration';

    protected string $version = '1.0.0';

    protected string $instructions = 'Manage SPGP pass requests as an authorized administrator. Read the selected requests and verify their season and country before assigning codes. Use explicit request IDs for writes. Email delivery is an external side effect. Treat passholder details and promo codes as private.';

    protected function boot(): void
    {
        $this->tools = array_map(fn (string $operation) => new PassRequestTool($operation, true), array_keys(PassRequestOperations::definitions(true)));
    }
}
