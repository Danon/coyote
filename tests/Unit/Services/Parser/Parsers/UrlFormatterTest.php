<?php

namespace Tests\Unit\Services\Parser\Parsers;

use Collective\Html\HtmlBuilder;
use Coyote\Services\Parser\Parsers\Parentheses\ParenthesesParser;
use Coyote\Services\Parser\Parsers\Parentheses\SymmetricParenthesesChunks;
use Coyote\Services\Parser\Parsers\UrlFormatter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UrlFormatterTest extends TestCase
{
    /**
     * @test
     */
    public function shouldParseLink()
    {
        // given
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum'), $this->parser());

        // when
        $result = $formatter->parse('text 4pr.net/Forum text');

        // then
        $this->assertEquals('text <a>4pr.net/Forum</a> text', $result);
    }

    /**
     * @test
     */
    public function shouldParseLink_htmlEntities()
    {
        // given
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum&param'), $this->parser());

        // when
        $result = $formatter->parse('text 4pr.net/Forum&param text');

        // then
        $this->assertEquals('text <a>4pr.net/Forum&amp;param</a> text', $result);
    }

    /**
     * @test
     */
    public function shouldTruncateLongLink()
    {
        // given
        $longUrl = 'https://scrutinizer-ci.com/g/adam-boduch/coyote/inspections/8778b728-ef73-4167-8092-424a57a8e66d';
        $formatter = new UrlFormatter('', $this->htmlExpect($longUrl), $this->parser());

        // when
        $result = $formatter->parse("link: $longUrl");

        // then
        $this->assertEquals('link: <a>https://scrutinizer-ci.com/g/[...]8-ef73-4167-8092-424a57a8e66d</a>', $result);
    }

    /**
     * @test
     */
    public function shouldNotTruncateLongLink_hostLink()
    {
        // given
        $longUrl = 'https://4pr.net/g/adam-boduch/coyote/inspections/8778b728-ef73-4167-8092-424a57a8e66d';
        $formatter = new UrlFormatter('4pr.net', $this->htmlExpect($longUrl), $this->parser());

        // when
        $result = $formatter->parse("link: $longUrl");

        // then
        $this->assertEquals('link: <a>https://4pr.net/g/adam-boduch/coyote/inspections/8778b728-ef73-4167-8092-424a57a8e66d</a>', $result);
    }

    /**
     * @test
     */
    public function shouldIncludeParenthesis()
    {
        // given
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum/(text)'), $this->parser());

        // when
        $result = $formatter->parse('text 4pr.net/Forum/(text) text');

        // then
        $this->assertEquals('text <a>4pr.net/Forum/(text)</a> text', $result);
    }

    /**
     * @test
     */
    public function shouldHandleLinksSeparatedByAnOpenParenthesis()
    {
        // given
        $formatter = new UrlFormatter('', $this->html(), $this->parser());

        // when
        $result = $formatter->parse('4pr.net/Forum(https://4pr.net/Forum');

        // then
        $this->assertEquals('<a>4pr.net/Forum</a>(<a>https://4pr.net/Forum</a>', $result);
    }

    /**
     * @test
     */
    public function shouldHandleLinksSeparatedByAnOpenParenthesis_ifItResemblesADomain()
    {
        // given
        $formatter = new UrlFormatter('', $this->html(), $this->parser());

        // when
        $result = $formatter->parse('4pr.net/Forum(4pr.net/Forum');

        // then
        $this->assertEquals('<a>4pr.net/Forum</a>(<a>4pr.net/Forum</a>', $result);
    }

    /**
     * @test
     */
    public function shouldIncludeNestedParenthesis()
    {
        // given
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum/(t(ex)t)'), $this->parser());

        // when
        $result = $formatter->parse('text 4pr.net/Forum/(t(ex)t) text');

        // then
        $this->assertEquals('text <a>4pr.net/Forum/(t(ex)t)</a> text', $result);
    }

    /**
     * @test
     */
    public function shouldNotIncludeUnmatchedParenthesis()
    {
        // given
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum/(text)'), $this->parser());

        // when
        $result = $formatter->parse('text 4pr.net/Forum/(text)( text');

        // then
        $this->assertEquals('text <a>4pr.net/Forum/(text)</a>( text', $result);
    }

    /**
     * @test
     */
    public function shouldNotIncludeUnmatchedNestedParenthesis()
    {
        // given
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum/'), $this->parser());

        // when
        $result = $formatter->parse('4pr.net/Forum/(tex(t)');

        // then
        $this->assertEquals('<a>4pr.net/Forum/</a>(tex(t)', $result);
    }

    /**
     * @test
     */
    public function shouldHandleCatastrophicBacktracking_withUnmatchedParenthesis()
    {
        // given
        $errorProneLink = 'http://4pr.net/Forum/(long_long_long_long_long';
        $formatter = new UrlFormatter('', $this->htmlExpect('http://4pr.net/Forum/'), $this->parser());

        // when
        $result = $formatter->parse($errorProneLink);

        // then
        $this->assertEquals('<a>http://4pr.net/Forum/</a>(long_long_long_long_long', $result);
    }

    private function htmlExpect(string $expectedHref = null): HtmlBuilder
    {
        /** @var HtmlBuilder|MockObject $html */
        $html = $this->createMock(HtmlBuilder::class);
        $html
            ->expects($this->once())
            ->method('link')
            ->will($this->returnCallback(function (string $href, string $title) use ($expectedHref): string {
                if ($expectedHref) {
                    $this->assertEquals($expectedHref, $href, 'Failed asserting that parsed link contains expected href attribute');
                }
                return '<a>' . htmlentities($title) . '</a>'; // We're mocking HtmlBuilder and trust it will encode the title
            }));
        return $html;
    }

    private function html(): HtmlBuilder
    {
        /** @var HtmlBuilder|MockObject $html */
        $html = $this->createMock(HtmlBuilder::class);
        $html->method('link')->will($this->returnCallback(function (string $href, string $title): string {
            return "<a>$title</a>";
        }));
        return $html;
    }

    private function parser(): ParenthesesParser
    {
        return new ParenthesesParser(new SymmetricParenthesesChunks(), 4);
    }
}
