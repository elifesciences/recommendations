elifePipeline {
    def commit
    stage 'Checkout', {
        checkout scm
        commit = elifeGitRevision()
    }

    node('containers-jenkins-plugin') {
        stage 'Build image', {
                checkout scm
                dockerComposeBuild commit
        }

        stage 'Project tests', {
            dockerProjectTests 'recommendations', commit
        }
    }

    elifeMainlineOnly {
        stage 'End2end tests', {

            elifeSpectrum(
                deploy: [
                    stackname: 'recommendations--end2end',
                    revision: commit,
                    folder: '/srv/recommendations'
                ],
                marker: 'recommendations'
            )
        }

        stage 'Deploy on continuumtest', {
            lock('recommendations--continuumtest') {
                builderDeployRevision 'recommendations--continuumtest', commit
                builderSmokeTests 'recommendations--continuumtest', '/srv/recommendations'
            }
        }

        stage 'Approval', {
            elifeGitMoveToBranch commit, 'approved'
        }
    }
}
