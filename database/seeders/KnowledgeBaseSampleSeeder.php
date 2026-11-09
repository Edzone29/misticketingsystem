<?php

namespace Database\Seeders;

use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

class KnowledgeBaseSampleSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'head@demo.university.test')->first()
            ?? User::whereHas('role', fn ($query) => $query->where('name', 'mis_head'))->orderBy('id')->first();

        if (! $author) {
            return;
        }

        $articles = [
            [
                'slug' => 'sample-connect-to-campus-wifi',
                'title' => '[SAMPLE] Connect to campus Wi-Fi',
                'category' => 'Sample - Network and Internet',
                'body' => <<<'ARTICLE'
DEMONSTRATION ARTICLE. Confirm the official campus network name and any campus-specific connection steps with the MIS Office before publishing this guide.

1. Make sure Wi-Fi is turned on and Airplane mode is off.
2. Check that you are in an area with campus wireless coverage.
3. Select the official campus network and sign in using your assigned account, if prompted. Never share your password or verification code with support staff.
4. If the device was previously connected, turn Wi-Fi off and on, then try again. Restart the device if the problem continues.
5. Check whether another approved campus service works. Note any error message and the time it occurred.

If you still cannot connect, submit a ticket with your building, room, device type, approximate time, and the exact error message. Do not include your password or one-time verification code.
ARTICLE,
            ],
            [
                'slug' => 'sample-password-and-account-access',
                'title' => '[SAMPLE] Password and account access',
                'category' => 'Sample - Accounts',
                'body' => <<<'ARTICLE'
DEMONSTRATION ARTICLE. Replace the reset-page reference below with the college-approved account recovery process before publishing this guide.

1. Check that you entered the correct college email address or account name and that Caps Lock is off.
2. Use only the official college password reset page or contact channel. Do not use links sent by unknown people.
3. If a reset message does not arrive, check the spam folder and verify that you entered the correct email address.
4. If the account is locked, you suspect someone else accessed it, or the reset process fails, contact the MIS Office through the approved helpdesk.

MIS staff will never need your password or one-time verification code. Include the affected service and any visible error in your ticket, but keep secrets out of the description and attachments.
ARTICLE,
            ],
            [
                'slug' => 'sample-printer-basic-checks',
                'title' => '[SAMPLE] Basic printer troubleshooting',
                'category' => 'Sample - Printers',
                'body' => <<<'ARTICLE'
DEMONSTRATION ARTICLE. Follow local equipment and safety procedures. Do not open printer panels or attempt repairs unless authorized.

1. Check that the printer is powered on and has no visible paper, toner, or error warning.
2. Confirm that you selected the correct printer in the application and that it is the printer assigned to your office or room.
3. Try printing a short, non-sensitive test page. If a job is stuck, cancel only your own job and try once more.
4. If the issue continues, note the printer name or asset tag, its location, the application used, and the displayed error.

Submit a ticket with those details. Do not include documents containing student, employee, financial, or other confidential information unless MIS has provided an approved secure process.
ARTICLE,
            ],
            [
                'slug' => 'sample-submit-a-useful-mis-ticket',
                'title' => '[SAMPLE] Submit a useful MIS support ticket',
                'category' => 'Sample - Helpdesk Guidance',
                'body' => <<<'ARTICLE'
DEMONSTRATION ARTICLE. Review the college ticket categories and contact details before publishing this guide.

To help MIS diagnose a concern, include:
- A short subject describing the problem.
- The affected system, device, or service and its asset tag when available.
- The building and room where the issue occurs.
- What you were trying to do, what happened, and any exact error message.
- When the issue began, whether other people are affected, and what basic steps you have already tried.

Attach a screenshot only when it helps explain the problem. Before uploading, remove passwords, verification codes, personal records, and unrelated sensitive information. Submit one ticket for the same issue and use its ticket number for follow-up.
ARTICLE,
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeBaseArticle::updateOrCreate(
                ['slug' => $article['slug']],
                $article + [
                    'author_id' => $author->id,
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
