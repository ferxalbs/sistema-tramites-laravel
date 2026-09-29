<?php

namespace App\Services;

use Illuminate\Http\Request;

class SupportKnowledgeBase
{
    /**
     * @return array{
     *     assistant_topics: array<string, array{question: string, answer: string}>,
     *     faq: array<string, array{question: string, answer: string}>,
     *     tutorials: list<array{title: string, steps: list<string>}>,
     *     role_guide: list<array{title: string, description: string}>,
     *     contact: array<string, string>,
     *     whatsapp_url: ?string
     * }
     */
    public function forRequest(Request $request): array
    {
        $topics = config('support.topics', []);
        $user = $request->user();
        $context = $request->routeIs('login')
            ? 'login'
            : ($user === null ? 'public' : ($user->rol === 'estudiante' ? 'student' : 'dashboard'));
        $number = $this->whatsappNumber((string) config('support.contact.whatsapp', ''));
        $contact = array_filter([
            'email' => (string) config('support.contact.email', ''),
            'phone' => (string) config('support.contact.phone', ''),
            'whatsapp' => $number === null ? '' : '+'.$number,
            'hours' => (string) config('support.contact.hours', ''),
            'address' => (string) config('support.contact.address', ''),
        ], static fn (string $value): bool => trim($value) !== '');

        return [
            'assistant_topics' => array_map(
                static fn (array $topic): array => [
                    'question' => $topic['question'],
                    'answer' => $topic['answer'],
                ],
                array_filter($topics, static fn (array $topic): bool => ($topic['assistant'] ?? false) === true),
            ),
            'faq' => array_map(
                static fn (array $topic): array => [
                    'question' => $topic['question'],
                    'answer' => $topic['answer'],
                ],
                array_filter($topics, static fn (array $topic): bool => ($topic['faq'] ?? false) === true),
            ),
            'tutorials' => config('support.tutorials', []),
            'role_guide' => $user === null ? [] : config('support.role_guides.'.$user->rol, []),
            'contact' => $contact,
            'whatsapp_url' => $number === null ? null : $this->whatsappUrl($number, $context),
        ];
    }

    private function whatsappNumber(string $configuredNumber): ?string
    {
        $number = preg_replace('/\D+/', '', $configuredNumber) ?? '';

        if (strlen($number) < 8 || strlen($number) > 15 || preg_match('/^0+$/', $number) === 1) {
            return null;
        }

        return $number;
    }

    private function whatsappUrl(string $number, string $context): string
    {
        $message = config('support.messages.'.$context, config('support.messages.public'));

        return 'https://wa.me/'.$number.'?text='.rawurlencode((string) $message);
    }
}
