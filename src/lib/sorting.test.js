import { test, describe } from 'node:test'
import assert from 'node:assert/strict'
import { sortProducts, toggleSort, SORT_IDS } from './sorting.js'

/** Mirrors the API's response shape, including its nulls. */
const product = (name, price, wasPrice, reviews) => ({
  name,
  price,
  was_price: wasPrice,
  saving: wasPrice === null ? 0 : wasPrice - price,
  reviews,
})

const catalogue = [
  product('Nature Patterned Envelope Files', 100, null, 90),
  product('Manhatten Veneer Photobook', 799, 999, 70),
  product('Really Useful Box', 799, 999, null),
  product('Silver Mesh Tier Drawers', 2599, null, 80),
  product('14 Litre Waste Bin - Warm Grey', 299, null, 30),
  product('Punched Folder Pockets', 100, 200, 70),
]

const names = (list) => list.map((p) => p.name)

describe('sortProducts', () => {
  test('orders by price ascending', () => {
    const sorted = sortProducts(catalogue, 'price')
    assert.deepEqual(
      sorted.map((p) => p.price),
      [100, 100, 299, 799, 799, 2599],
    )
  })

  test('orders by review ascending, treating a missing score as zero', () => {
    const sorted = sortProducts(catalogue, 'review')
    assert.deepEqual(
      sorted.map((p) => p.reviews),
      [null, 30, 70, 70, 80, 90],
    )
  })

  test('orders by saving ascending, treating no was_price as no saving', () => {
    const sorted = sortProducts(catalogue, 'saving')
    assert.deepEqual(
      sorted.map((p) => p.saving),
      [0, 0, 0, 100, 200, 200],
    )
  })

  test('orders by name alphabetically', () => {
    const sorted = sortProducts(catalogue, 'name')
    assert.equal(sorted[0].name, '14 Litre Waste Bin - Warm Grey')
    assert.equal(sorted.at(-1).name, 'Silver Mesh Tier Drawers')
  })

  test('sorts names containing numbers numerically, not lexically', () => {
    const bins = [product('9 Litre Bin', 1, null, null), product('14 Litre Bin', 2, null, null)]
    assert.deepEqual(names(sortProducts(bins, 'name')), ['9 Litre Bin', '14 Litre Bin'])
  })

  test('is stable, so tied products keep their original order', () => {
    // Both cost 100, and Nature Patterned appears first in the catalogue.
    const sorted = sortProducts(catalogue, 'price')
    assert.deepEqual(names(sorted).slice(0, 2), [
      'Nature Patterned Envelope Files',
      'Punched Folder Pockets',
    ])
  })

  test('returns the original order when no sort is active', () => {
    assert.deepEqual(names(sortProducts(catalogue, null)), names(catalogue))
  })

  test('ignores an unrecognised sort rather than throwing', () => {
    assert.deepEqual(names(sortProducts(catalogue, 'colour')), names(catalogue))
  })

  test('never mutates the array it is given', () => {
    const original = names(catalogue)
    for (const id of SORT_IDS) sortProducts(catalogue, id)
    assert.deepEqual(names(catalogue), original)
  })

  test('handles an empty catalogue', () => {
    assert.deepEqual(sortProducts([], 'price'), [])
  })
})

describe('toggleSort', () => {
  test('activates a sort when none is active', () => {
    assert.equal(toggleSort(null, 'price'), 'price')
  })

  test('replaces the active sort, so only one is ever active', () => {
    assert.equal(toggleSort('price', 'name'), 'name')
  })

  test('clears the sort when the active one is clicked again', () => {
    assert.equal(toggleSort('price', 'price'), null)
  })
})
