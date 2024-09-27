import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../node_modules/@emulsify/core/scripts/loadYaml';
import { setupTwig, namespaces } from '../../../../config/emulsify-core/storybook/setupTwig';

setupTwig(Twig);

describe('select', () => {
  it('can render a select form item', async () => {
    const { container } = await render(
      join(__dirname, 'select.twig'),
      loadYaml(join(__dirname, 'select.yml')),
      namespaces,
    );

    expect(container).toMatchInlineSnapshot(`
      <div>

        <option>
          Choose an Option
        </option>


        <option>
          Option 1
        </option>


        <option>
          Option 2
        </option>


        <option>
          Option 3
        </option>


        <option>
          Option 4
        </option>


      </div>
    `);
  });
});
