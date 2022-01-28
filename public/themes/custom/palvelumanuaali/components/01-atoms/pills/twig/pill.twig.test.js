import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../util/loadYaml';
import { setupTwig } from '../../../../.storybook/setupTwig';

setupTwig(Twig);

describe('Twig Pill', () => {
  it('can render a pill with the standard data', async () => {
    const { container } = await render(
      join(__dirname, 'pill.twig'),
      loadYaml(join(__dirname, 'pill.yml')),
    );
    expect(container).toMatchSnapshot();
  });

  it('can render a pill with the alternative data', async () => {
    const { container } = await render(
      join(__dirname, 'pill.twig'),
      loadYaml(join(__dirname, 'pill-alt.yml')),
    );
    expect(container).toMatchSnapshot();
  });
});
