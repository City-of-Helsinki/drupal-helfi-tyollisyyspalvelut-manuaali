const configOverrides = {
  stories: [
    '../../../../components/**/*.stories.mdx',
    '../../../../components/**/*.stories.@(js|jsx|ts|tsx)',
  ],
  staticDirs: [
    '../../../../assets/images',
    '../../../../assets/images/icons',
    '../../../../dist',
  ],
  addons: [
    '@storybook/addon-a11y',
    '@storybook/addon-links',
    '@storybook/addon-essentials',
  ],
};

module.exports = {configOverrides};

