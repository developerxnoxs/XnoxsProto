<?php

namespace XnoxsProto\TL\Types;

use XnoxsProto\TL\BinaryWriter;

/**
 * InputMediaPoll — serializes a poll for messages.sendMedia.
 *
 * TL schema:
 *
 *   inputMediaPoll#0f94e5f1
 *     flags:#
 *     poll:Poll
 *     correct_answers:flags.0?Vector<bytes>    (quiz mode only)
 *     solution:flags.1?string                  (quiz explanation)
 *     solution_entities:flags.1?Vector<MsgEnt> (quiz explanation entities)
 *     = InputMedia;
 *
 *   poll#d5529d06
 *     id:long
 *     flags:#
 *     closed:flags.2?true           — poll is already closed
 *     public_voters:flags.3?true    — results are visible to all
 *     multiple_choice:flags.4?true  — multiple answers allowed
 *     quiz:flags.5?true             — quiz mode (one correct answer)
 *     question:TextWithEntities
 *     answers:Vector<PollAnswer>
 *     close_period:flags.6?int      — auto-close after N seconds
 *     close_date:flags.7?int        — unix ts to close
 *     = Poll;
 *
 *   pollAnswer#6ca9c2e9
 *     text:TextWithEntities
 *     option:bytes                  — unique byte key per answer
 *     = PollAnswer;
 *
 *   textWithEntities#dcf52858
 *     text:string
 *     entities:Vector<MessageEntity>
 *     = TextWithEntities;
 */
class InputMediaPoll
{
    const CTOR_INPUT_MEDIA_POLL  = 0x0f94e5f1;
    const CTOR_POLL              = 0x58747131;  // poll#58747131 (layer 155+)
    const CTOR_POLL_ANSWER       = 0xff16e2ca;  // pollAnswer#ff16e2ca
    const CTOR_TEXT_WITH_ENT     = 0x751f3146;  // textWithEntities#751f3146
    const CTOR_VECTOR            = 0x1cb5c415;

    // poll flags — layer 155+ schema:
    //   closed:flags.0         public_voters:flags.1
    //   multiple_choice:flags.2   quiz:flags.3
    //   close_period:flags.4    close_date:flags.5
    const FLAG_CLOSED          = 1 << 0;  // 0x01
    const FLAG_PUBLIC_VOTERS   = 1 << 1;  // 0x02
    const FLAG_MULTIPLE_CHOICE = 1 << 2;  // 0x04
    const FLAG_QUIZ            = 1 << 3;  // 0x08

    private string $question;
    private array  $answers;        // ['text' => string, ...]
    private bool   $publicVoters   = false;
    private bool   $multipleChoice = false;
    private bool   $quiz           = false;
    private int    $closePeriod    = 0;  // 0 = no auto-close

    private array  $correctAnswers = [];  // bytes keys for quiz correct answers
    private string $solution       = '';  // quiz explanation

    private function __construct(string $question, array $answers)
    {
        $this->question = $question;
        $this->answers  = $answers;
    }

    /**
     * Create a simple poll.
     * @param string   $question  Poll question
     * @param string[] $answers   Array of answer text strings (2–10 answers)
     */
    public static function create(string $question, array $answers): self
    {
        return new self($question, array_values($answers));
    }

    public function setPublicVoters(bool $v = true): self  { $this->publicVoters   = $v; return $this; }
    public function setMultipleChoice(bool $v = true): self { $this->multipleChoice = $v; return $this; }
    public function setClosePeriod(int $seconds): self      { $this->closePeriod    = $seconds; return $this; }

    /**
     * Enable quiz mode.
     * @param int    $correctIndex  0-based index of the correct answer
     * @param string $solution      Explanation shown after answering (optional)
     */
    public function setQuiz(int $correctIndex, string $solution = ''): self
    {
        $this->quiz           = true;
        $this->correctAnswers = [chr($correctIndex)]; // single-byte option key
        $this->solution       = $solution;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Serialization
    // -------------------------------------------------------------------------

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CTOR_INPUT_MEDIA_POLL);

        // flags for inputMediaPoll
        $flags = 0;
        if (!empty($this->correctAnswers)) $flags |= 0x1; // correct_answers present
        if ($this->solution !== '')        $flags |= 0x2; // solution present
        $writer->writeInt($flags);

        // poll#d5529d06
        $this->serializePoll($writer);

        // correct_answers: flags.0?Vector<bytes>
        if (!empty($this->correctAnswers)) {
            $writer->writeInt(self::CTOR_VECTOR);
            $writer->writeInt(count($this->correctAnswers));
            foreach ($this->correctAnswers as $ans) {
                $writer->writeBytes($ans);
            }
        }

        // solution: flags.1?string
        if ($this->solution !== '') {
            $writer->writeString($this->solution);
            // solution_entities: flags.1?Vector<MessageEntity> — empty
            $writer->writeInt(self::CTOR_VECTOR);
            $writer->writeInt(0);
        }
    }

    private function serializePoll(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CTOR_POLL);
        $writer->writeLong(0); // id (server assigns)

        $pollFlags = 0;
        if ($this->publicVoters)   $pollFlags |= self::FLAG_PUBLIC_VOTERS;
        if ($this->multipleChoice) $pollFlags |= self::FLAG_MULTIPLE_CHOICE;
        if ($this->quiz)           $pollFlags |= self::FLAG_QUIZ;
        if ($this->closePeriod > 0) $pollFlags |= (1 << 4); // close_period:flags.4
        $writer->writeInt($pollFlags);

        // question: TextWithEntities
        $this->writeTextWithEntities($writer, $this->question);

        // answers: Vector<PollAnswer>
        $writer->writeInt(self::CTOR_VECTOR);
        $writer->writeInt(count($this->answers));
        foreach ($this->answers as $idx => $text) {
            $this->serializeAnswer($writer, $text, chr($idx));
        }

        // close_period: flags.4?int
        if ($this->closePeriod > 0) {
            $writer->writeInt($this->closePeriod);
        }
    }

    private function serializeAnswer(BinaryWriter $writer, string $text, string $optionKey): void
    {
        $writer->writeInt(self::CTOR_POLL_ANSWER);
        $this->writeTextWithEntities($writer, $text);
        $writer->writeBytes($optionKey);
    }

    private function writeTextWithEntities(BinaryWriter $writer, string $text): void
    {
        $writer->writeInt(self::CTOR_TEXT_WITH_ENT);
        $writer->writeString($text);
        // entities: empty Vector<MessageEntity>
        $writer->writeInt(self::CTOR_VECTOR);
        $writer->writeInt(0);
    }
}
