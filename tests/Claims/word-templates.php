<?php

declare(strict_types=1);

use MarkdownOffice\Documentation\Tests\Support\Site;
use MarkdownOffice\Documentation\Tests\Support\Template;
use MarkdownWord\Exception\NoContentControl;
use MarkdownWord\Template\ContentControl;
use MarkdownWord\Template\ContentFallback;
use MarkdownWord\Template\MarkdownTemplate;

/**
 * The claims the pages make about rendering into a design that already exists.
 *
 * A template is a Word document somebody made in Word, and the page says what the library
 * makes of it: the control is matched on its tag, a filled field is unlinked, a template
 * with no control takes the content at the end, and the result is a document whatever went
 * in. Each is a claim about what the library does, and each is checked against the library
 * that has to do it.
 */

/**
 * The content the claims put into a template.
 *
 * The same words every time, because a claim is about where the words went and not about
 * what they were.
 */
const CONTENT = 'The content.';

it('says that body is this library own convention, and names the constant', function () {
    says(Site::WORD_TEMPLATES, '`body` is this library\'s convention, not Word\'s');

    expect(ContentControl::BODY)->toBe('body');
});

it('matches a control on its tag rather than on its alias', function () {
    says(Site::WORD_TEMPLATES, 'The control is matched on its **tag**');

    // The control's alias says something else on purpose: a library that matched on the
    // alias would not find this one, and the content would go to the end of the body
    // instead of into the control.
    $template = Template::withControl('body', 'A different label');

    $output = Template::written((new MarkdownTemplate($template))->insert(CONTENT));

    expect(Template::xmlOf($output))->toContain(CONTENT)
        ->and(Template::xmlOf($output))->not->toContain('w:alias w:val="A different label"');
});

it('says that filled fields are unlinked, and takes the field away', function () {
    says(Site::WORD_TEMPLATES, 'Filled fields are unlinked');

    $template = Template::withControl();

    $output = Template::written(
        (new MarkdownTemplate($template))
            ->fields(['reference' => 'REF-1'])
            ->insert(CONTENT),
    );

    $xml = Template::xmlOf($output);

    expect($xml)->toContain('REF-1')
        ->and($xml)->not->toContain('MERGEFIELD');
});

it('says that a template with no control takes the content at the end of its body', function () {
    says(Site::WORD_TEMPLATES, 'takes the content **at the end of its body**');

    $template = Template::withoutControl();

    $output = Template::written((new MarkdownTemplate($template))->insert(CONTENT));

    expect(Template::textOf($output))->toContain(CONTENT);
});

it('says that a template with no control can be refused instead, and refuses it by name', function () {
    says(Site::WORD_TEMPLATES, 'says so and refuses');

    $template = Template::withoutControl();

    // The refusal comes when the document is written rather than when the content is
    // put in: the content has somewhere to go until the moment it has to be there.
    expect(
        fn () => (new MarkdownTemplate($template))
            ->contentFallback(ContentFallback::Error)
            ->insert(CONTENT)
            ->toString(),
    )->toThrow(NoContentControl::class);
});

it('says that a filled-in template is always a document, and writes one', function () {
    says(Site::WORD_TEMPLATES, 'a filled-in template is always a document');

    $template = Template::dotx(Template::withControl());

    $output = Template::written((new MarkdownTemplate($template))->insert(CONTENT));

    // The result is a document whatever went in, and the file says so: the content type
    // is the one a document has and not the one a template has.
    $zip = new \ZipArchive();
    $zip->open($output);
    $declared = (string) $zip->getFromName('[Content_Types].xml');
    $zip->close();

    expect($declared)->toContain('wordprocessingml.document.main+xml')
        ->and($declared)->not->toContain('wordprocessingml.template.main+xml');
});
