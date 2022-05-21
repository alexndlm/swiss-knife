<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace EasyCI20220521\Symfony\Component\Config\Resource;

use EasyCI20220521\Symfony\Component\Finder\Finder;
use EasyCI20220521\Symfony\Component\Finder\Glob;
/**
 * GlobResource represents a set of resources stored on the filesystem.
 *
 * Only existence/removal is tracked (not mtimes.)
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 *
 * @implements \IteratorAggregate<string, \SplFileInfo>
 */
class GlobResource implements \IteratorAggregate, \EasyCI20220521\Symfony\Component\Config\Resource\SelfCheckingResourceInterface
{
    /**
     * @var string
     */
    private $prefix;
    /**
     * @var string
     */
    private $pattern;
    /**
     * @var bool
     */
    private $recursive;
    /**
     * @var string
     */
    private $hash;
    /**
     * @var bool
     */
    private $forExclusion;
    /**
     * @var mixed[]
     */
    private $excludedPrefixes;
    /**
     * @var int
     */
    private $globBrace;
    /**
     * @param string $prefix    A directory prefix
     * @param string $pattern   A glob pattern
     * @param bool   $recursive Whether directories should be scanned recursively or not
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $prefix, string $pattern, bool $recursive, bool $forExclusion = \false, array $excludedPrefixes = [])
    {
        \ksort($excludedPrefixes);
        $resolvedPrefix = \realpath($prefix) ?: (\file_exists($prefix) ? $prefix : \false);
        $this->pattern = $pattern;
        $this->recursive = $recursive;
        $this->forExclusion = $forExclusion;
        $this->excludedPrefixes = $excludedPrefixes;
        $this->globBrace = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;
        if (\false === $resolvedPrefix) {
            throw new \InvalidArgumentException(\sprintf('The path "%s" does not exist.', $prefix));
        }
        $this->prefix = $resolvedPrefix;
    }
    public function getPrefix() : string
    {
        return $this->prefix;
    }
    public function __toString() : string
    {
        return 'glob.' . $this->prefix . (int) $this->recursive . $this->pattern . (int) $this->forExclusion . \implode("\0", $this->excludedPrefixes);
    }
    /**
     * {@inheritdoc}
     */
    public function isFresh(int $timestamp) : bool
    {
        $hash = $this->computeHash();
        $this->hash = $this->hash ?? $hash;
        return $this->hash === $hash;
    }
    /**
     * @internal
     */
    public function __sleep() : array
    {
        $this->hash = $this->hash ?? $this->computeHash();
        return ['prefix', 'pattern', 'recursive', 'hash', 'forExclusion', 'excludedPrefixes'];
    }
    /**
     * @internal
     */
    public function __wakeup() : void
    {
        $this->globBrace = \defined('GLOB_BRACE') ? \GLOB_BRACE : 0;
    }
    public function getIterator() : \Traversable
    {
        if (!\file_exists($this->prefix) || !$this->recursive && '' === $this->pattern) {
            return;
        }
        $prefix = \str_replace('\\', '/', $this->prefix);
        $paths = null;
        if (\strncmp($this->prefix, 'phar://', \strlen('phar://')) !== 0 && \strpos($this->pattern, '/**/') === \false) {
            if ($this->globBrace || \strpos($this->pattern, '{') === \false) {
                $paths = \glob($this->prefix . $this->pattern, \GLOB_NOSORT | $this->globBrace);
            } elseif (\strpos($this->pattern, '\\') === \false || !\preg_match('/\\\\[,{}]/', $this->pattern)) {
                foreach ($this->expandGlob($this->pattern) as $p) {
                    $paths[] = \glob($this->prefix . $p, \GLOB_NOSORT);
                }
                $paths = \array_merge(...$paths);
            }
        }
        if (null !== $paths) {
            \natsort($paths);
            foreach ($paths as $path) {
                if ($this->excludedPrefixes) {
                    $normalizedPath = \str_replace('\\', '/', $path);
                    do {
                        if (isset($this->excludedPrefixes[$dirPath = $normalizedPath])) {
                            continue 2;
                        }
                    } while ($prefix !== $dirPath && $dirPath !== ($normalizedPath = \dirname($dirPath)));
                }
                if (\is_file($path)) {
                    (yield $path => new \SplFileInfo($path));
                }
                if (!\is_dir($path)) {
                    continue;
                }
                if ($this->forExclusion) {
                    (yield $path => new \SplFileInfo($path));
                    continue;
                }
                if (!$this->recursive || isset($this->excludedPrefixes[\str_replace('\\', '/', $path)])) {
                    continue;
                }
                $files = \iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS), function (\SplFileInfo $file, $path) {
                    return !isset($this->excludedPrefixes[\str_replace('\\', '/', $path)]) && '.' !== $file->getBasename()[0];
                }), \RecursiveIteratorIterator::LEAVES_ONLY));
                \uksort($files, 'strnatcmp');
                foreach ($files as $path => $info) {
                    if ($info->isFile()) {
                        (yield $path => $info);
                    }
                }
            }
            return;
        }
        if (!\class_exists(\EasyCI20220521\Symfony\Component\Finder\Finder::class)) {
            throw new \LogicException(\sprintf('Extended glob pattern "%s" cannot be used as the Finder component is not installed.', $this->pattern));
        }
        $finder = new \EasyCI20220521\Symfony\Component\Finder\Finder();
        $regex = \EasyCI20220521\Symfony\Component\Finder\Glob::toRegex($this->pattern);
        if ($this->recursive) {
            $regex = \substr_replace($regex, '(/|$)', -2, 1);
        }
        $prefixLen = \strlen($this->prefix);
        foreach ($finder->followLinks()->sortByName()->in($this->prefix) as $path => $info) {
            $normalizedPath = \str_replace('\\', '/', $path);
            if (!\preg_match($regex, \substr($normalizedPath, $prefixLen)) || !$info->isFile()) {
                continue;
            }
            if ($this->excludedPrefixes) {
                do {
                    if (isset($this->excludedPrefixes[$dirPath = $normalizedPath])) {
                        continue 2;
                    }
                } while ($prefix !== $dirPath && $dirPath !== ($normalizedPath = \dirname($dirPath)));
            }
            (yield $path => $info);
        }
    }
    private function computeHash() : string
    {
        $hash = \hash_init('md5');
        foreach ($this->getIterator() as $path => $info) {
            \hash_update($hash, $path . "\n");
        }
        return \hash_final($hash);
    }
    private function expandGlob(string $pattern) : array
    {
        $segments = \preg_split('/\\{([^{}]*+)\\}/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $paths = [$segments[0]];
        $patterns = [];
        for ($i = 1; $i < \count($segments); $i += 2) {
            $patterns = [];
            foreach (\explode(',', $segments[$i]) as $s) {
                foreach ($paths as $p) {
                    $patterns[] = $p . $s . $segments[1 + $i];
                }
            }
            $paths = $patterns;
        }
        $j = 0;
        foreach ($patterns as $i => $p) {
            if (\strpos($p, '{') !== \false) {
                $p = $this->expandGlob($p);
                \array_splice($paths, $i + $j, 1, $p);
                $j += \count($p) - 1;
            }
        }
        return $paths;
    }
}
