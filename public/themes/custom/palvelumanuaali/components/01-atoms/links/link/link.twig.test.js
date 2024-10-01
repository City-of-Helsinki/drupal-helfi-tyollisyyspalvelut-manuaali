import { join } from 'path';
import { render, Twig } from 'twig-testing-library';

import loadYaml from '../../../../node_modules/@emulsify/core/scripts/loadYaml';
import { setupTwig } from '../../../../config/emulsify-core/storybook/setupTwig';

setupTwig(Twig);

describe('link', () => {
  it('can render a link', async () => {
    const { container } = await render(
      join(__dirname, 'link.twig'),
      loadYaml(join(__dirname, 'link.yml')),
    );

    expect(container).toMatchInlineSnapshot(`
      <div>



        <a
          class="link"
          href="https://github.com/palvelumanuaali-ds/palvelumanuaali-design-system"
          target="_blank"
        >

            This is my link text

        </a>


      </div>
    `);
  });
});
