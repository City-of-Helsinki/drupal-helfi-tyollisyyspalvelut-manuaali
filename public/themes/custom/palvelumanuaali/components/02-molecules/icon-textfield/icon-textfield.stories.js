import iconTextfield from './icon-textfield.twig';

import iconTextfieldData from './icon-textfield.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Icon textfield' };

export const iconTextfieldExample = () => iconTextfield(iconTextfieldData);
