import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../node_modules/@emulsify/core/scripts/loadYaml';
import { setupTwig, namespaces } from '../../../../config/emulsify-core/storybook/setupTwig';

setupTwig(Twig);

describe('main-menu', () => {
  it('can render an main menu', async () => {
    const { container } = await render(
      join(__dirname, 'main-menu.twig'),
      loadYaml(join(__dirname, 'main-menu.yml')),
      namespaces,
    );

    expect(container).toMatchSnapshot();
  });
});
