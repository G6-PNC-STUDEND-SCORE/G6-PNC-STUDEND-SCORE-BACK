<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailDomainRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailDomainRuleController extends Controller
{
    // GET /email-domain-rules — list all domain -> role sign-in rules
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => EmailDomainRule::with('role')->orderBy('domain')->get(),
        ]);
    }

    // POST /email-domain-rules — add a new domain -> role rule
    public function store(Request $request): JsonResponse
    {
        $domain = Str::lower(trim((string) $request->input('domain'), " \t\n\r\0\x0B@"));
        $request->merge(['domain' => $domain]);

        $request->validate([
            'domain' => 'required|string|max:255|unique:email_domain_rules,domain',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $rule = EmailDomainRule::create([
            ...$request->only('domain', 'role_id'),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Sign-in rule for '{$rule->domain}' created successfully.",
            'data' => $rule->load('role'),
        ], 201);
    }

    // PUT /email-domain-rules/{emailDomainRule} — update a domain -> role rule
    public function update(Request $request, EmailDomainRule $emailDomainRule): JsonResponse
    {
        $domain = Str::lower(trim((string) $request->input('domain'), " \t\n\r\0\x0B@"));
        $request->merge(['domain' => $domain]);

        $request->validate([
            'domain' => 'required|string|max:255|unique:email_domain_rules,domain,' . $emailDomainRule->id,
            'role_id' => 'required|integer|exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        $emailDomainRule->update($request->only('domain', 'role_id', 'is_active'));

        return response()->json([
            'success' => true,
            'message' => "Sign-in rule for '{$emailDomainRule->domain}' updated successfully.",
            'data' => $emailDomainRule->fresh('role'),
        ]);
    }

    // DELETE /email-domain-rules/{emailDomainRule} — remove a domain -> role rule
    public function destroy(EmailDomainRule $emailDomainRule): JsonResponse
    {
        $domain = $emailDomainRule->domain;
        $emailDomainRule->delete();

        return response()->json([
            'success' => true,
            'message' => "Sign-in rule for '{$domain}' deleted successfully.",
        ]);
    }
}
