import React from 'react';
import renderer from 'react-test-renderer';
import { render, fireEvent, screen } from '@testing-library/react';

import Pill from './Pill.component';

describe('Pill', () => {
  it('renders a pill component around the given children', () => {
    expect.assertions(1);
    expect(renderer.create(<div>Click Me!</div>).toJSON())
      .toMatchInlineSnapshot(`
      <div
        className="pill"
      >
        Click Me!
      </div>
    `);
  });
});
