<?php

declare(strict_types=1);

namespace SqrtSpace\SpaceTime\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use SqrtSpace\SpaceTime\Streams\SpaceTimeStream;
use SqrtSpace\SpaceTime\Checkpoint\CheckpointManager;

/**
 * Example Symfony command using SpaceTime
 */
class ProcessFileCommand extends Command
{
    protected static $defaultName = 'spacetime:process-file';
    protected static $defaultDescription = 'Process large files using SpaceTime streaming';
    
    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'Input file path')
            ->addArgument('output', InputArgument::REQUIRED, 'Output file path')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'File format (csv, json, text)', 'text')
            ->addOption('checkpoint', 'c', InputOption::VALUE_NONE, 'Enable checkpointing')
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter expression')
            ->addOption('transform', null, InputOption::VALUE_REQUIRED, 'Transform expression');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $inputFile = $input->getArgument('input');
        $outputFile = $input->getArgument('output');
        $format = $input->getOption('format');
        $useCheckpoint = $input->getOption('checkpoint');
        
        if (!file_exists($inputFile)) {
            $io->error("Input file not found: $inputFile");
            return Command::FAILURE;
        }
        
        $io->title('SpaceTime File Processor');
        $io->text([
            "Input: $inputFile",
            "Output: $outputFile",
            "Format: $format",
            "Checkpointing: " . ($useCheckpoint ? 'Enabled' : 'Disabled'),
        ]);
        
        try {
            // Create stream based on format
            $stream = match($format) {
                'csv' => SpaceTimeStream::fromCsv($inputFile),
                'json' => $this->createJsonStream($inputFile),
                default => SpaceTimeStream::fromFile($inputFile),
            };
            
            // Apply filters if specified
            if ($filter = $input->getOption('filter')) {
                $stream = $stream->filter($this->createFilterFunction($filter));
            }
            
            // Apply transformations if specified
            if ($transform = $input->getOption('transform')) {
                $stream = $stream->map($this->createTransformFunction($transform));
            }
            
            // Process with checkpoint support
            if ($useCheckpoint) {
                $this->processWithCheckpoint($stream, $outputFile, $format, $io);
            } else {
                $this->processStream($stream, $outputFile, $format, $io);
            }
            
            $io->success('File processed successfully!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Processing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function processStream(SpaceTimeStream $stream, string $outputFile, string $format, SymfonyStyle $io): void
    {
        $count = 0;
        $progressBar = $io->createProgressBar();
        
        // Process based on format
        match($format) {
            'csv' => $stream->toCsv($outputFile),
            'json' => $this->saveAsJson($stream, $outputFile),
            default => $stream->toFile($outputFile),
        };
        
        $progressBar->finish();
        $io->newLine();
    }
    
    private function processWithCheckpoint(SpaceTimeStream $stream, string $outputFile, string $format, SymfonyStyle $io): void
    {
        $checkpoint = new CheckpointManager('process_file_' . md5($outputFile));
        
        $checkpoint->wrap(function($state) use ($stream, $outputFile, $format, $io) {
            $processed = $state['processed'] ?? 0;
            $handle = fopen($outputFile, $processed > 0 ? 'a' : 'w');
            
            $stream->skip($processed)->each(function($item) use ($handle, &$processed, $checkpoint) {
                fwrite($handle, json_encode($item) . "\n");
                $processed++;
                
                if ($checkpoint->shouldCheckpoint()) {
                    $checkpoint->save(['processed' => $processed]);
                }
            });
            
            fclose($handle);
            
            return $processed;
        });
    }
    
    private function createJsonStream(string $file): SpaceTimeStream
    {
        return SpaceTimeStream::from(function() use ($file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if (is_array($data)) {
                foreach ($data as $item) {
                    yield $item;
                }
            }
        });
    }
    
    private function saveAsJson(SpaceTimeStream $stream, string $outputFile): void
    {
        $handle = fopen($outputFile, 'w');
        fwrite($handle, "[\n");
        
        $first = true;
        $stream->each(function($item) use ($handle, &$first) {
            if (!$first) {
                fwrite($handle, ",\n");
            }
            fwrite($handle, json_encode($item));
            $first = false;
        });
        
        fwrite($handle, "\n]");
        fclose($handle);
    }
    
    private function createFilterFunction(string $expression): callable
    {
        // Simple expression parser (in production, use a proper expression evaluator)
        return function($item) use ($expression) {
            // Example: "price > 100"
            if (preg_match('/(\w+)\s*([><=]+)\s*(.+)/', $expression, $matches)) {
                $field = $matches[1];
                $operator = $matches[2];
                $value = $matches[3];
                
                if (!isset($item[$field])) {
                    return false;
                }
                
                return match($operator) {
                    '>' => $item[$field] > $value,
                    '<' => $item[$field] < $value,
                    '>=' => $item[$field] >= $value,
                    '<=' => $item[$field] <= $value,
                    '=' => $item[$field] == $value,
                    default => true,
                };
            }
            
            return true;
        };
    }
    
    private function createTransformFunction(string $expression): callable
    {
        // Simple transformation (in production, use a proper expression evaluator)
        return function($item) use ($expression) {
            // Example: "upper(name)"
            if (preg_match('/(\w+)\((\w+)\)/', $expression, $matches)) {
                $function = $matches[1];
                $field = $matches[2];
                
                if (isset($item[$field])) {
                    $item[$field] = match($function) {
                        'upper' => strtoupper($item[$field]),
                        'lower' => strtolower($item[$field]),
                        'trim' => trim($item[$field]),
                        default => $item[$field],
                    };
                }
            }
            
            return $item;
        };
    }
}