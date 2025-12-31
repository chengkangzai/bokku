<?php

namespace App\Mcp\Tools\Payees;

use App\Models\Payee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeletePayeeTool extends Tool
{
    protected string $description = 'Delete a payee.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Payee ID is required.',
        ]);

        $payee = Payee::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $payee) {
            return Response::error('Payee not found or access denied.');
        }

        $name = $payee->name;
        $payee->delete();

        return Response::structured([
            'message' => "Payee '{$name}' deleted successfully.",
            'deleted' => true,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The payee ID to delete')
                ->required(),
        ];
    }
}
