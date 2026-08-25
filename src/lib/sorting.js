/**
 * Product sorting.
 *
 * The brief specifies:
 *   - four sorts: price, review, name and saving
 *   - always ascending
 *   - only one sort active at a time
 *   - no sort active by default
 *
 * "Only one active at a time" is enforced by modelling the active sort as a
 * single value rather than a set of independent toggles — an impossible state
 * (two sorts at once) simply cannot be represented.
 *
 * "No sort by default" means null is a real, reachable state, not just an
 * initial one: re-clicking the active sort returns to the catalogue's original
 * order.
 */

/** The sort controls, in the order they appear in the design. */
export const SORT_OPTIONS = [
  { id: 'price', label: 'Sort By Price' },
  { id: 'review', label: 'Sort By Review' },
  { id: 'name', label: 'Sort By Name' },
  { id: 'saving', label: 'Sort By Saving' },
]

export const SORT_IDS = SORT_OPTIONS.map((option) => option.id)

/**
 * Ascending comparators, one per sort.
 *
 * Products missing a review score are treated as 0 so they sort to the front
 * in ascending order. That is a deliberate choice rather than a side effect:
 * leaving null in place would make the comparator return NaN and produce an
 * implementation-defined ordering.
 */
const COMPARATORS = {
  price: (a, b) => a.price - b.price,

  review: (a, b) => (a.reviews ?? 0) - (b.reviews ?? 0),

  // Localised and numeric-aware so "14 Litre Waste Bin" orders against its
  // digits rather than character-by-character, where "14" would fall between
  // "1" and "2".
  name: (a, b) =>
    a.name.localeCompare(b.name, 'en-GB', {
      numeric: true,
      sensitivity: 'base',
    }),

  // The API already resolves saving to 0 for products with no was_price, so
  // they group at the front rather than needing a null guard here.
  saving: (a, b) => a.saving - b.saving,
}

/**
 * Returns a new array of products in ascending order for the given sort.
 *
 * A null (or unrecognised) sort returns the products untouched, which is the
 * catalogue's original order.
 *
 * Never mutates its input: the unsorted order has to stay intact so that
 * clearing the sort can return to it. Array.prototype.sort is stable, so
 * products that tie — and several here share a price — keep their original
 * relative order instead of shuffling between renders.
 *
 * @param {Array<object>} products
 * @param {string|null} sortId
 * @returns {Array<object>}
 */
export function sortProducts(products, sortId) {
  const comparator = sortId ? COMPARATORS[sortId] : undefined

  if (!comparator) {
    return products
  }

  return [...products].sort(comparator)
}

/**
 * Resolves the next active sort when a control is clicked.
 *
 * Selecting a different sort replaces the current one; selecting the active
 * sort clears it.
 *
 * @param {string|null} current
 * @param {string} clicked
 * @returns {string|null}
 */
export function toggleSort(current, clicked) {
  return current === clicked ? null : clicked
}
