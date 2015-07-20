<?php
/**
 * Copyright (C) 2015 David Young
 *
 * Defines the view lexer
 */
namespace Opulence\Views\Compilers\Lexers;
use Opulence\Views\Compilers\Lexers\Tokens\Token;
use Opulence\Views\Compilers\Lexers\Tokens\TokenTypes;
use Opulence\Views\ITemplate;
use RuntimeException;

class Lexer implements ILexer
{
    /** @var ITemplate The current template being lexed */
    private $template = null;
    /** @var string The current input being lexed */
    private $input = "";
    /** @var Token[] The list of tokens generated by the lexer */
    private $tokens = [];
    /** @var array The directive delimiters */
    private $directiveDelimiters = [];
    /** @var array The sanitized tag delimiters */
    private $sanitizedTagDelimiters = [];
    /** @var array The unsanitized tag delimiters */
    private $unsanitizedTagDelimiters = [];
    /** @var int The cursor (current position) of the lexer */
    private $cursor = 0;
    /** @var int The current line the lexer is on */
    private $line = 1;
    /** @var string The buffer of any expressions that fall outside tags/directives */
    private $expressionBuffer = "";
    /** @var array Caches information for the current stream, which improves performance by 4x */
    private $streamCache = ["cursor" => PHP_INT_MAX, "length" => null, "stream" => ""];

    /**
     * Lexes input into a list of tokens
     *
     * @param ITemplate $template The template that's being lexed
     * @param string $input The input to lex
     * @return Token[] The list of tokens
     * @throws RuntimeException Thrown if there was an invalid token
     */
    public function lex(ITemplate $template, $input)
    {
        $this->initializeVariables($template, $input);
        $this->lexExpression();

        return $this->tokens;
    }

    /**
     * Gets whether or not we're at the end of the file
     *
     * @return bool True if we're at the end of the file, otherwise false
     */
    private function atEOF()
    {
        return $this->getStream() == "";
    }

    /**
     * Flushes the expression buffer
     */
    private function flushExpressionBuffer()
    {
        if($this->expressionBuffer != "")
        {
            $this->tokens[] = new Token(TokenTypes::T_EXPRESSION, $this->expressionBuffer, $this->line);
            // Account for all the new lines
            $this->line += substr_count($this->expressionBuffer, PHP_EOL);
            $this->expressionBuffer = "";
        }
    }

    /**
     * Gets the character under the cursor
     *
     * @return string The current character
     */
    private function getCurrentChar()
    {
        return substr($this->input, $this->cursor, 1);
    }

    /**
     * Gets a sorted mapping of opening statement delimiters to the lexing methods to call on a match
     *
     * @return array The mapping of opening statement delimiters to the methods
     */
    private function getStatementLexingMethods()
    {
        $statements = [
            $this->directiveDelimiters[0] => "lexDirectiveStatement",
            $this->sanitizedTagDelimiters[0] => "lexSanitizedTagStatement",
            $this->unsanitizedTagDelimiters[0] => "lexUnsanitizedTagStatement",
            "<?php" => "lexPHPStatement",
            "<?" => "lexPHPStatement"
        ];

        /**
         * In case one delimiter is a substring of the other ("{{" and "{{!"), we want to sort the delimiters
         * so that the longest delimiters come first
         */
        uksort($statements, function ($a, $b)
        {
            if(strlen($a) > strlen($b))
            {
                return -1;
            }
            else
            {
                return 1;
            }
        });

        return $statements;
    }

    /**
     * Gets the stream of input that has not yet been lexed
     *
     * @param int|null $cursor The position of the cursor
     * @param int|null $length The length of input to return
     * @return string The stream of input
     */
    private function getStream($cursor = null, $length = null)
    {
        if($cursor === null)
        {
            $cursor = $this->cursor;
        }

        // If the cached length isn't the same or if the cursor has actually gone backwards, use the original input
        if($this->streamCache["length"] !== $length || $this->streamCache["cursor"] > $cursor)
        {
            $this->streamCache["cursor"] = $cursor;
            $this->streamCache["length"] = $length;

            if($length === null)
            {
                $this->streamCache["stream"] = substr($this->input, $cursor);
            }
            else
            {
                $this->streamCache["stream"] = substr($this->input, $cursor, $length);
            }
        }
        elseif($this->streamCache["length"] === $length && $this->streamCache["cursor"] !== $cursor)
        {
            // Grab the substring from the cached stream
            $cursorDifference = $cursor - $this->streamCache["cursor"];

            if($length === null)
            {
                $this->streamCache["stream"] = substr($this->streamCache["stream"], $cursorDifference);
            }
            else
            {
                $this->streamCache["stream"] = substr($this->streamCache["stream"], $cursorDifference, $length);
            }

            $this->streamCache["cursor"] = $cursor;
        }

        return $this->streamCache["stream"];
    }

    /**
     * Initializes instance variables for lexing
     *
     * @param ITemplate $template The template that's being lexed
     * @param string $input The input to lex
     */
    private function initializeVariables(ITemplate $template, $input)
    {
        $this->template = $template;
        $this->directiveDelimiters = $this->template->getDelimiters(ITemplate::DELIMITER_TYPE_DIRECTIVE);
        $this->sanitizedTagDelimiters = $this->template->getDelimiters(ITemplate::DELIMITER_TYPE_SANITIZED_TAG);
        $this->unsanitizedTagDelimiters = $this->template->getDelimiters(ITemplate::DELIMITER_TYPE_UNSANITIZED_TAG);
        $this->input = $input;
        $this->tokens = [];
        $this->cursor = 0;
        $this->line = 1;
        $this->expressionBuffer = "";
    }

    /**
     * Lexes an expression that is delimited with tags
     *
     * @param string $closeDelimiter The close delimiter
     */
    private function lexDelimitedExpression($closeDelimiter)
    {
        $expressionBuffer = "";
        $newLinesAfterExpression = 0;

        while(!$this->matches($closeDelimiter, false) && !$this->atEOF())
        {
            $currentChar = $this->getCurrentChar();

            if($currentChar == PHP_EOL)
            {
                if(trim($expressionBuffer) == "")
                {
                    $this->line++;
                }
                else
                {
                    $newLinesAfterExpression++;
                }
            }

            $expressionBuffer .= $currentChar;
            $this->cursor++;
        }

        $expressionBuffer = trim($expressionBuffer);

        if($expressionBuffer != "")
        {
            $this->tokens[] = new Token(TokenTypes::T_EXPRESSION, $expressionBuffer, $this->line);
            $this->line += $newLinesAfterExpression;
        }
    }

    /**
     * Lexes a statement that is comprised of a delimited statement
     *
     * @param string $openTokenType The open token type
     * @param string $openDelimiter The open delimiter
     * @param string $closeTokenType The close token type
     * @param string $closeDelimiter The close delimiter
     * @param bool $closeDelimiterOptional Whether or not the close delimiter is optional
     */
    private function lexDelimitedExpressionStatement(
        $openTokenType,
        $openDelimiter,
        $closeTokenType,
        $closeDelimiter,
        $closeDelimiterOptional
    )
    {
        $this->flushExpressionBuffer();
        $this->tokens[] = new Token($openTokenType, $openDelimiter, $this->line);
        $this->lexDelimitedExpression($closeDelimiter);

        if(!$this->matches($closeDelimiter) && !$closeDelimiterOptional)
        {
            throw new RuntimeException(
                sprintf(
                    "Expected %s, found %s on line %d",
                    $closeDelimiter,
                    $this->getStream($this->cursor, strlen($closeDelimiter)),
                    $this->line
                )
            );
        }

        $this->tokens[] = new Token($closeTokenType, $closeDelimiter, $this->line);
    }

    /**
     * Lexes a directive statement
     */
    private function lexDirectiveExpression()
    {
        $this->lexDirectiveName();

        if($this->matches("("))
        {
            $parenthesisLevel = 1;
            $newLinesAfterExpression = 0;
            $expressionBuffer = "";

            while(!$this->matches($this->directiveDelimiters[1], false))
            {
                $currentChar = $this->getCurrentChar();

                if($currentChar == "(")
                {
                    $expressionBuffer .= $currentChar;
                    $parenthesisLevel++;
                }
                elseif($currentChar == ")")
                {
                    $parenthesisLevel--;

                    if($parenthesisLevel != 0)
                    {
                        $expressionBuffer .= $currentChar;
                    }
                }
                elseif($currentChar == PHP_EOL)
                {
                    if(trim($expressionBuffer) == "")
                    {
                        $this->line++;
                    }
                    else
                    {
                        $newLinesAfterExpression++;
                    }
                }
                else
                {
                    $expressionBuffer .= $currentChar;
                }

                $this->cursor++;
            }

            if($parenthesisLevel != 0)
            {
                throw new RuntimeException(
                    sprintf(
                        "Unmatched parenthesis on line %d",
                        $this->line
                    )
                );
            }

            $expressionBuffer = trim($expressionBuffer);
            $this->tokens[] = new Token(TokenTypes::T_EXPRESSION, $expressionBuffer, $this->line);
            $this->line += $newLinesAfterExpression;
        }
    }

    /**
     * Lexes a directive name
     */
    private function lexDirectiveName()
    {
        $name = "";
        $newLinesAfterName = 0;

        do
        {
            $currentChar = $this->getCurrentChar();

            // Handle new line characters between directive delimiters
            if($currentChar == PHP_EOL)
            {
                if(trim($name) == "")
                {
                    $this->line++;
                }
                else
                {
                    $newLinesAfterName++;
                }
            }

            $name .= $currentChar;
            $this->cursor++;
        }while(preg_match("/^[a-zA-Z0-9_\s]$/", $this->getCurrentChar()) === 1);

        $name = trim($name);

        if($name == "")
        {
            throw new RuntimeException(
                sprintf(
                    "Expected %s on line %d, none found",
                    TokenTypes::T_DIRECTIVE_NAME,
                    $this->line
                )
            );
        }

        $this->tokens[] = new Token(TokenTypes::T_DIRECTIVE_NAME, $name, $this->line);
        $this->line += $newLinesAfterName;
    }

    /**
     * Lexes a directive statement
     *
     * @throws RuntimeException Thrown if the statement has an invalid token
     */
    private function lexDirectiveStatement()
    {
        $this->flushExpressionBuffer();
        $this->tokens[] = new Token(TokenTypes::T_DIRECTIVE_OPEN, $this->directiveDelimiters[0], $this->line);
        $this->lexDirectiveExpression();

        if(!$this->matches($this->directiveDelimiters[1]))
        {
            throw new RuntimeException(
                sprintf(
                    "Expected %s, found %s on line %d",
                    $this->directiveDelimiters[1],
                    $this->getStream($this->cursor, strlen($this->directiveDelimiters[1])),
                    $this->line
                )
            );
        }

        $this->tokens[] = new Token(TokenTypes::T_DIRECTIVE_CLOSE, $this->directiveDelimiters[1], $this->line);
    }

    /**
     * Lexes an expression
     *
     * @throws RuntimeException Thrown if there was an invalid token
     */
    private function lexExpression()
    {
        $statementMethods = $this->getStatementLexingMethods();

        while(!$this->atEOF())
        {
            $matchedStatement = false;

            // This is essentially a foreach loop that can be reset
            while(list($statementOpenDelimiter, $methodName) = each($statementMethods))
            {
                if($this->matches($statementOpenDelimiter))
                {
                    // This is an unescaped statement
                    $matchedStatement = true;
                    $this->{$methodName}();

                    // Now that we've matched, we want to reset the loop so that longest delimiters are matched first
                    reset($statementMethods);
                }
                elseif($this->getCurrentChar() == "\\")
                {
                    // Now that we know we're on an escape character, spend the resources to check for a match
                    if($this->matches("\\$statementOpenDelimiter"))
                    {
                        // This is an escaped statement
                        $this->expressionBuffer .= $statementOpenDelimiter;
                    }
                }
            }

            // Handle any text outside statements
            if(!$matchedStatement && !$this->atEOF())
            {
                $this->expressionBuffer .= $this->getCurrentChar();
                $this->cursor++;

                // Keep on going if we're seeing alphanumeric text
                while(ctype_alnum($this->getCurrentChar()))
                {
                    $this->expressionBuffer .= $this->getCurrentChar();
                    $this->cursor++;
                }

                $this->lexExpression();
            }

            $this->flushExpressionBuffer();
        }
    }

    /**
     * Lexes a PHP statement
     */
    private function lexPHPStatement()
    {
        $this->lexDelimitedExpressionStatement(
            TokenTypes::T_PHP_OPEN_TAG,
            "<?php",
            TokenTypes::T_PHP_CLOSE_TAG,
            "?>",
            true
        );
    }

    /**
     * Lexes a sanitized tag statement
     *
     * @throws RuntimeException Thrown if the statement has an invalid token
     */
    private function lexSanitizedTagStatement()
    {
        $this->lexDelimitedExpressionStatement(
            TokenTypes::T_SANITIZED_TAG_OPEN,
            $this->sanitizedTagDelimiters[0],
            TokenTypes::T_SANITIZED_TAG_CLOSE,
            $this->sanitizedTagDelimiters[1],
            false
        );
    }

    /**
     * Lexes an unsanitized tag statement
     *
     * @throws RuntimeException Thrown if the statement has an invalid token
     */
    private function lexUnsanitizedTagStatement()
    {
        $this->lexDelimitedExpressionStatement(
            TokenTypes::T_UNSANITIZED_TAG_OPEN,
            $this->unsanitizedTagDelimiters[0],
            TokenTypes::T_UNSANITIZED_TAG_CLOSE,
            $this->unsanitizedTagDelimiters[1],
            false
        );
    }

    /**
     * Gets whether or not the input at the cursor matches an expected value
     *
     * @param string $expected The expected string
     * @param bool $shouldConsume Whether or not to consume the expected value on a match
     * @param int|null $cursor The cursor position to match at
     * @return bool True if the input at the cursor matches the expected value, otherwise false
     */
    private function matches($expected, $shouldConsume = true, $cursor = null)
    {
        $stream = $this->getStream($cursor);
        $expectedLength = strlen($expected);

        if(substr($stream, 0, $expectedLength) == $expected)
        {
            if($shouldConsume)
            {
                $this->cursor += $expectedLength;
            }

            return true;
        }

        return false;
    }
}