<?php

namespace App\Services;

class SEOAnalyzerService
{
    public function analyzePage($title, $description, $content, $keywords = null)
    {
        $score = 0;
        $suggestions = [];
        $checks = [];

        // Title: 50-60 characters is optimal.
        $titleLength = strlen((string) $title);
        if ($titleLength >= 50 && $titleLength <= 60) {
            $score += 15;
            $checks['title_length'] = ['status' => 'good', 'message' => 'Title length is optimal'];
        } elseif ($titleLength >= 30 && $titleLength <= 70) {
            $score += 8;
            $checks['title_length'] = ['status' => 'warning', 'message' => 'Title length could be optimized'];
            $suggestions[] = $titleLength < 50
                ? 'Consider making your title longer (50-60 chars optimal)'
                : 'Consider shortening your title (50-60 chars optimal)';
        } else {
            $checks['title_length'] = ['status' => 'error', 'message' => 'Title length needs improvement'];
            $suggestions[] = 'Title should be between 50-60 characters';
        }

        // Meta description: 120-160 characters is optimal.
        $descLength = strlen((string) $description);
        if ($descLength >= 120 && $descLength <= 160) {
            $score += 20;
            $checks['description_length'] = ['status' => 'good', 'message' => 'Meta description length is optimal'];
        } elseif ($descLength > 0) {
            $score += 10;
            $checks['description_length'] = ['status' => 'warning', 'message' => 'Meta description length could be optimized'];
            $suggestions[] = $descLength < 120
                ? 'Consider making your description longer (120-160 chars optimal)'
                : 'Consider shortening your description (120-160 chars optimal)';
        } else {
            $checks['description_length'] = ['status' => 'error', 'message' => 'Meta description needs improvement'];
            $suggestions[] = 'Meta description should be between 120-160 characters';
        }

        // Content: at least 30 words scores well.
        $wordCount = str_word_count(strip_tags((string) $content));
        if ($wordCount >= 30) {
            $score += 20;
            $checks['content_length'] = ['status' => 'good', 'message' => 'Content length is sufficient'];
        } elseif ($wordCount >= 10) {
            $score += 8;
            $checks['content_length'] = ['status' => 'warning', 'message' => 'Content could be longer'];
            $suggestions[] = 'Consider adding more content (30+ words recommended)';
        } else {
            $checks['content_length'] = ['status' => 'error', 'message' => 'Content is too short'];
            $suggestions[] = 'Add more content (minimum 30 words recommended)';
        }

        // Keywords: 3-5 focus keywords is appropriate.
        if ($keywords) {
            $keywordArray = array_filter(array_map('trim', explode(',', (string) $keywords)));
            if (count($keywordArray) <= 5) {
                $score += 10;
                $checks['keywords'] = ['status' => 'good', 'message' => 'Keyword count is appropriate'];
            } else {
                $score += 5;
                $checks['keywords'] = ['status' => 'warning', 'message' => 'Too many keywords'];
                $suggestions[] = 'Focus on 3-5 main keywords';
            }
        }

        // Readability: average words per sentence.
        $sentences = preg_split('/[.!?]+/', strip_tags((string) $content));
        $sentenceCount = max(count(array_filter($sentences, fn ($sentence) => trim($sentence) !== '')), 1);
        $avgWordsPerSentence = $wordCount / $sentenceCount;

        if ($avgWordsPerSentence <= 20) {
            $score += 20;
            $checks['readability'] = ['status' => 'good', 'message' => 'Content is easy to read'];
        } elseif ($avgWordsPerSentence <= 25) {
            $score += 10;
            $checks['readability'] = ['status' => 'warning', 'message' => 'Content readability could be improved'];
            $suggestions[] = 'Consider shorter sentences for better readability';
        } else {
            $checks['readability'] = ['status' => 'error', 'message' => 'Content is hard to read'];
            $suggestions[] = 'Break up long sentences for better readability';
        }

        return [
            'score' => min($score, 100),
            'grade' => $this->getGrade($score),
            'checks' => $checks,
            'suggestions' => $suggestions,
        ];
    }

    public function getGrade($score)
    {
        if ($score >= 90) {
            return 'excellent';
        }
        if ($score >= 80) {
            return 'good';
        }
        if ($score >= 60) {
            return 'average';
        }
        if ($score >= 40) {
            return 'poor';
        }

        return 'critical';
    }
}
