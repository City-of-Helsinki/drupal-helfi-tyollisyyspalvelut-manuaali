import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../node_modules/@emulsify/core/scripts/loadYaml';
import { setupTwig } from '../../../../config/emulsify-core/storybook/setupTwig';

setupTwig(Twig);

describe('Twig Button', () => {
  it('can render a button with the standard data', async () => {
    const { container } = await render(
      join(__dirname, 'button.twig'),
      loadYaml(join(__dirname, 'button.yml')),
    );
    expect(container).toMatchSnapshot();
  });

  it('can render a button with the alternative data', async () => {
    const { container } = await render(
      join(__dirname, 'button.twig'),
      loadYaml(join(__dirname, 'button-alt.yml')),
    );
    expect(container).toMatchSnapshot();
  });
});
