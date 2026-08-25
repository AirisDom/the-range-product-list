/**
 * Presentation helpers.
 *
 * Prices arrive from the API as integer pence — 100, 799, 2599 — which avoids
 * floating point rounding in transit. They are only converted to a display
 * string here, at the edge.
 */

const GBP = new Intl.NumberFormat('en-GB', {
  style: 'currency',
  currency: 'GBP',
})

/**
 * Formats integer pence as sterling: 100 -> "£1.00", 2599 -> "£25.99".
 *
 * @param {number} pence
 * @returns {string}
 */
export function formatPrice(pence) {
  return GBP.format(pence / 100)
}

/**
 * Formats a review score as it appears in the design: 90 -> "90% Review Score".
 *
 * @param {number} score
 * @returns {string}
 */
export function formatReviewScore(score) {
  return `${score}% Review Score`
}
