<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Normalizes list request parameters (search, sort, pagination, filters) and
 * applies them consistently across index screens.
 */
final class ListQuery
{
    public const PER_PAGE_DEFAULT = 15;

    public const PER_PAGE_MAX = 100;

    private function __construct(
        public readonly ?string $search,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $perPage,
        /** @var array<string, mixed> */
        public readonly array $filters,
    ) {}

    /**
     * @param  array{defaultSort?: string, allowedSorts?: list<string>, defaultPerPage?: int, filters?: list<string>}  $options
     */
    public static function fromRequest(Request $request, array $options = []): self
    {
        $defaultSort = $options['defaultSort'] ?? 'created_at';
        $allowedSorts = $options['allowedSorts'] ?? [];
        $defaultPerPage = $options['defaultPerPage'] ?? self::PER_PAGE_DEFAULT;

        $sort = (string) $request->string('sort', $defaultSort);
        if ($allowedSorts !== [] && ! in_array($sort, $allowedSorts, true)) {
            $sort = $defaultSort;
        }

        $direction = strtolower((string) $request->string('direction', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $perPage = (int) $request->integer('per_page', $defaultPerPage);
        $perPage = max(5, min($perPage, self::PER_PAGE_MAX));

        $search = trim((string) $request->string('search'));
        $filters = $request->only($options['filters'] ?? []);

        return new self(
            $search === '' ? null : $search,
            $sort,
            $direction,
            $perPage,
            $filters,
        );
    }

    /**
     * @param  list<string>  $searchColumns
     */
    public function apply(Builder $query, array $searchColumns = []): Builder
    {
        if ($this->search !== null && $searchColumns !== []) {
            $term = '%'.$this->escapeLike($this->search).'%';

            $query->where(function (Builder $inner) use ($searchColumns, $term) {
                foreach ($searchColumns as $column) {
                    $inner->orWhere($column, 'like', $term);
                }
            });
        }

        return $query->orderBy($this->sort, $this->direction);
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->paginate($this->perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
            'filters' => $this->filters,
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
